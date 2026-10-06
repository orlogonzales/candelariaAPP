<?php

declare(strict_types=1);

namespace Aplicacion\Catalogo;

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Configuracion\ConfiguracionServicio;
use Aplicacion\Entidades\CategoriaItem;
use Aplicacion\Entidades\HistorialTarifaItem;
use Aplicacion\Entidades\HistorialTarifaPaquete;
use Aplicacion\Entidades\ItemComercial;
use Aplicacion\Entidades\OfertaItemEdicion;
use Aplicacion\Entidades\OfertaPaqueteEdicion;
use Aplicacion\Entidades\Paquete;
use Aplicacion\Entidades\TarifaItemEdicion;
use Aplicacion\Entidades\TarifaPaqueteEdicion;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Excepciones\ConflictoConcurrenciaExcepcion;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\CategoriaItemRepositorio;
use Aplicacion\Repositorios\EdicionRepositorio;
use Aplicacion\Repositorios\HistorialTarifaRepositorio;
use Aplicacion\Repositorios\ItemComercialRepositorio;
use Aplicacion\Repositorios\OfertaItemEdicionRepositorio;
use Aplicacion\Repositorios\OfertaPaqueteEdicionRepositorio;
use Aplicacion\Repositorios\PaqueteRepositorio;
use Aplicacion\Repositorios\TarifaItemEdicionRepositorio;
use Aplicacion\Repositorios\TarifaPaqueteEdicionRepositorio;
use InvalidArgumentException;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use PDO;
use RuntimeException;

/**
 * Servicio soberano de dominio para el Catálogo Comercial, Paquetes, Ofertas y Tarifas.
 * Implementa:
 * 1. Aislamiento multi-tenant estricto y Anti-IDOR en todas las operaciones.
 * 2. Catálogo maestro transversal por organización (Categorías, Ítems, Paquetes y Composición).
 * 3. Habilitación de ofertas contextualizadas por edición anual (ediciones_candelaria).
 * 4. Capacidad referencial meramente informativa comercial (no inventario transaccional).
 * 5. Tarifas con clave foránea real por oferta y moneda snapshot inmutable de plataforma.
 * 6. Historial append-only de modificaciones de tarifa con actor polimórfico soberano.
 * 7. Control de concurrencia optimista (version_bloqueo) desacoplado de HTTP.
 * 8. RBAC estricto y auditoría transaccional append-only.
 * 9. Cero borrado físico de información comercial histórica.
 */
class CatalogoServicio
{
    private PDO $pdo;

    public function __construct(
        private CategoriaItemRepositorio $categoriaRepo,
        private ItemComercialRepositorio $itemRepo,
        private PaqueteRepositorio $paqueteRepo,
        private OfertaItemEdicionRepositorio $ofertaItemRepo,
        private OfertaPaqueteEdicionRepositorio $ofertaPaqueteRepo,
        private TarifaItemEdicionRepositorio $tarifaItemRepo,
        private TarifaPaqueteEdicionRepositorio $tarifaPaqueteRepo,
        private HistorialTarifaRepositorio $historialRepo,
        private EdicionRepositorio $edicionRepo,
        private AutorizacionServicio $authzServicio,
        private AuditoriaRepositorio $auditoriaRepo,
        private ConfiguracionServicio $configServicio,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
    }

    // =========================================================================
    // 1. GESTIÓN DE CATEGORÍAS DE CATÁLOGO
    // =========================================================================

    public function crearCategoria(
        int $organizacionId,
        string $codigo,
        string $nombre,
        ?string $descripcion,
        int $orden,
        ContextoOperacion $contexto
    ): CategoriaItem {
        $this->validarPermiso('catalogo.categorias.gestionar', $contexto);
        $this->validarTenantContexto($organizacionId, $contexto);

        $codigoNorm = strtoupper(trim($codigo));
        $nombreNorm = trim($nombre);

        $existente = $this->categoriaRepo->buscarPorCodigo($codigoNorm, $organizacionId);
        if ($existente !== null) {
            throw new InvalidArgumentException("Ya existe una categoría con el código '{$codigoNorm}' en esta organización.");
        }

        $categoria = new CategoriaItem(
            id: null,
            organizacionId: $organizacionId,
            codigo: $codigoNorm,
            nombre: $nombreNorm,
            descripcion: $descripcion ? trim($descripcion) : null,
            orden: $orden,
            estado: EstadoCatalogo::ACTIVO
        );

        $creada = $this->categoriaRepo->guardar($categoria);

        $this->auditoriaRepo->registrar(
            contexto: $contexto,
            modulo: 'catalogo',
            accion: 'CREAR_CATEGORIA_CATALOGO',
            entidadTipo: 'categoria_item',
            entidadId: (string) $creada->id,
            datosPrevios: null,
            datosNuevos: $creada->toArray()
        );

        return $creada;
    }

    public function actualizarCategoria(
        int $organizacionId,
        int $categoriaId,
        string $nombre,
        ?string $descripcion,
        int $orden,
        ContextoOperacion $contexto
    ): CategoriaItem {
        $this->validarPermiso('catalogo.categorias.gestionar', $contexto);
        $this->validarTenantContexto($organizacionId, $contexto);

        $categoria = $this->categoriaRepo->buscarPorId($categoriaId, $organizacionId);
        if ($categoria === null) {
            throw new InvalidArgumentException("Categoría no encontrada o no pertenece a la organización.");
        }

        $previos = $categoria->toArray();

        $actualizada = new CategoriaItem(
            id: $categoria->id,
            organizacionId: $organizacionId,
            codigo: $categoria->codigo,
            nombre: trim($nombre),
            descripcion: $descripcion ? trim($descripcion) : null,
            orden: $orden,
            estado: $categoria->estado
        );

        $this->categoriaRepo->actualizar($actualizada);

        $this->auditoriaRepo->registrar(
            contexto: $contexto,
            modulo: 'catalogo',
            accion: 'EDITAR_CATEGORIA_CATALOGO',
            entidadTipo: 'categoria_item',
            entidadId: (string) $categoriaId,
            datosPrevios: $previos,
            datosNuevos: $actualizada->toArray()
        );

        return $this->categoriaRepo->buscarPorId($categoriaId, $organizacionId);
    }

    public function cambiarEstadoCategoria(
        int $organizacionId,
        int $categoriaId,
        EstadoCatalogo $nuevoEstado,
        ContextoOperacion $contexto
    ): bool {
        $this->validarPermiso('catalogo.categorias.gestionar', $contexto);
        $this->validarTenantContexto($organizacionId, $contexto);

        $categoria = $this->categoriaRepo->buscarPorId($categoriaId, $organizacionId);
        if ($categoria === null) {
            throw new InvalidArgumentException("Categoría no encontrada o no pertenece a la organización.");
        }

        $previos = $categoria->toArray();
        $ok = $this->categoriaRepo->cambiarEstado($categoriaId, $organizacionId, $nuevoEstado);

        $this->auditoriaRepo->registrar(
            contexto: $contexto,
            modulo: 'catalogo',
            accion: 'CAMBIAR_ESTADO_CATEGORIA_CATALOGO',
            entidadTipo: 'categoria_item',
            entidadId: (string) $categoriaId,
            datosPrevios: $previos,
            datosNuevos: ['estado' => $nuevoEstado->value]
        );

        return $ok;
    }

    // =========================================================================
    // 2. GESTIÓN DE ÍTEMS COMERCIALES
    // =========================================================================

    public function crearItem(
        int $organizacionId,
        int $categoriaId,
        string $codigo,
        string $nombre,
        TipoItemComercial $tipo,
        string $unidadMedida,
        ?string $descripcion,
        ContextoOperacion $contexto
    ): ItemComercial {
        $this->validarPermiso('catalogo.items.gestionar', $contexto);
        $this->validarTenantContexto($organizacionId, $contexto);

        // Anti-IDOR: Categoría debe existir y pertenecer al mismo tenant
        $categoria = $this->categoriaRepo->buscarPorId($categoriaId, $organizacionId);
        if ($categoria === null) {
            throw new InvalidArgumentException("La categoría especificada no existe o no pertenece a la organización.");
        }

        // Validación de unidad de medida cerrada
        $unidadNorm = strtoupper(trim($unidadMedida));
        $unidadEnum = UnidadMedidaItem::tryFrom($unidadNorm);
        if ($unidadEnum === null) {
            throw new InvalidArgumentException("La unidad de medida '{$unidadMedida}' no es válida.");
        }

        $codigoNorm = strtoupper(trim($codigo));
        $existente = $this->itemRepo->buscarPorCodigo($codigoNorm, $organizacionId);
        if ($existente !== null) {
            throw new InvalidArgumentException("Ya existe un ítem comercial con el código '{$codigoNorm}' en esta organización.");
        }

        $item = new ItemComercial(
            id: null,
            organizacionId: $organizacionId,
            categoriaId: $categoriaId,
            codigo: $codigoNorm,
            nombre: trim($nombre),
            tipo: $tipo,
            unidadMedida: $unidadEnum,
            descripcion: $descripcion ? trim($descripcion) : null,
            estado: EstadoCatalogo::ACTIVO
        );

        $creado = $this->itemRepo->guardar($item);

        $this->auditoriaRepo->registrar(
            contexto: $contexto,
            modulo: 'catalogo',
            accion: 'CREAR_ITEM_COMERCIAL',
            entidadTipo: 'item_comercial',
            entidadId: (string) $creado->id,
            datosPrevios: null,
            datosNuevos: $creado->toArray()
        );

        return $creado;
    }

    public function actualizarItem(
        int $organizacionId,
        int $itemId,
        int $categoriaId,
        string $nombre,
        TipoItemComercial $tipo,
        string $unidadMedida,
        ?string $descripcion,
        ContextoOperacion $contexto
    ): ItemComercial {
        $this->validarPermiso('catalogo.items.gestionar', $contexto);
        $this->validarTenantContexto($organizacionId, $contexto);

        $item = $this->itemRepo->buscarPorId($itemId, $organizacionId);
        if ($item === null) {
            throw new InvalidArgumentException("Ítem comercial no encontrado o no pertenece a la organización.");
        }

        $categoria = $this->categoriaRepo->buscarPorId($categoriaId, $organizacionId);
        if ($categoria === null) {
            throw new InvalidArgumentException("La categoría especificada no existe o no pertenece a la organización.");
        }

        $unidadNorm = strtoupper(trim($unidadMedida));
        $unidadEnum = UnidadMedidaItem::tryFrom($unidadNorm);
        if ($unidadEnum === null) {
            throw new InvalidArgumentException("La unidad de medida '{$unidadMedida}' no es válida.");
        }

        $previos = $item->toArray();

        $actualizado = new ItemComercial(
            id: $item->id,
            organizacionId: $organizacionId,
            categoriaId: $categoriaId,
            codigo: $item->codigo,
            nombre: trim($nombre),
            tipo: $tipo,
            unidadMedida: $unidadEnum,
            descripcion: $descripcion ? trim($descripcion) : null,
            estado: $item->estado
        );

        $this->itemRepo->actualizar($actualizado);

        $this->auditoriaRepo->registrar(
            contexto: $contexto,
            modulo: 'catalogo',
            accion: 'EDITAR_ITEM_COMERCIAL',
            entidadTipo: 'item_comercial',
            entidadId: (string) $itemId,
            datosPrevios: $previos,
            datosNuevos: $actualizado->toArray()
        );

        return $this->itemRepo->buscarPorId($itemId, $organizacionId);
    }

    public function cambiarEstadoItem(
        int $organizacionId,
        int $itemId,
        EstadoCatalogo $nuevoEstado,
        ContextoOperacion $contexto
    ): bool {
        $this->validarPermiso('catalogo.items.gestionar', $contexto);
        $this->validarTenantContexto($organizacionId, $contexto);

        $item = $this->itemRepo->buscarPorId($itemId, $organizacionId);
        if ($item === null) {
            throw new InvalidArgumentException("Ítem comercial no encontrado o no pertenece a la organización.");
        }

        $previos = $item->toArray();
        $ok = $this->itemRepo->cambiarEstado($itemId, $organizacionId, $nuevoEstado);

        $this->auditoriaRepo->registrar(
            contexto: $contexto,
            modulo: 'catalogo',
            accion: 'CAMBIAR_ESTADO_ITEM_COMERCIAL',
            entidadTipo: 'item_comercial',
            entidadId: (string) $itemId,
            datosPrevios: $previos,
            datosNuevos: ['estado' => $nuevoEstado->value]
        );

        return $ok;
    }

    // =========================================================================
    // 3. GESTIÓN DE PAQUETES COMERCIALES Y COMPOSICIÓN
    // =========================================================================

    public function crearPaquete(
        int $organizacionId,
        string $codigo,
        string $nombre,
        ?string $descripcion,
        ContextoOperacion $contexto
    ): Paquete {
        $this->validarPermiso('catalogo.paquetes.gestionar', $contexto);
        $this->validarTenantContexto($organizacionId, $contexto);

        $codigoNorm = strtoupper(trim($codigo));
        $existente = $this->paqueteRepo->buscarPorCodigo($codigoNorm, $organizacionId);
        if ($existente !== null) {
            throw new InvalidArgumentException("Ya existe un paquete comercial con el código '{$codigoNorm}' en esta organización.");
        }

        $paquete = new Paquete(
            id: null,
            organizacionId: $organizacionId,
            codigo: $codigoNorm,
            nombre: trim($nombre),
            descripcion: $descripcion ? trim($descripcion) : null,
            estado: EstadoCatalogo::ACTIVO
        );

        $creado = $this->paqueteRepo->guardar($paquete);

        $this->auditoriaRepo->registrar(
            contexto: $contexto,
            modulo: 'catalogo',
            accion: 'CREAR_PAQUETE',
            entidadTipo: 'paquete',
            entidadId: (string) $creado->id,
            datosPrevios: null,
            datosNuevos: $creado->toArray()
        );

        return $creado;
    }

    public function actualizarPaquete(
        int $organizacionId,
        int $paqueteId,
        string $nombre,
        ?string $descripcion,
        ContextoOperacion $contexto
    ): Paquete {
        $this->validarPermiso('catalogo.paquetes.gestionar', $contexto);
        $this->validarTenantContexto($organizacionId, $contexto);

        $paquete = $this->paqueteRepo->buscarPorId($paqueteId, $organizacionId);
        if ($paquete === null) {
            throw new InvalidArgumentException("Paquete no encontrado o no pertenece a la organización.");
        }

        $previos = $paquete->toArray();

        $actualizado = new Paquete(
            id: $paquete->id,
            organizacionId: $organizacionId,
            codigo: $paquete->codigo,
            nombre: trim($nombre),
            descripcion: $descripcion ? trim($descripcion) : null,
            estado: $paquete->estado
        );

        $this->paqueteRepo->actualizar($actualizado);

        $this->auditoriaRepo->registrar(
            contexto: $contexto,
            modulo: 'catalogo',
            accion: 'EDITAR_PAQUETE',
            entidadTipo: 'paquete',
            entidadId: (string) $paqueteId,
            datosPrevios: $previos,
            datosNuevos: $actualizado->toArray()
        );

        return $this->paqueteRepo->buscarPorId($paqueteId, $organizacionId);
    }

    public function cambiarEstadoPaquete(
        int $organizacionId,
        int $paqueteId,
        EstadoCatalogo $nuevoEstado,
        ContextoOperacion $contexto
    ): bool {
        $this->validarPermiso('catalogo.paquetes.gestionar', $contexto);
        $this->validarTenantContexto($organizacionId, $contexto);

        $paquete = $this->paqueteRepo->buscarPorId($paqueteId, $organizacionId);
        if ($paquete === null) {
            throw new InvalidArgumentException("Paquete no encontrado o no pertenece a la organización.");
        }

        $previos = $paquete->toArray();
        $ok = $this->paqueteRepo->cambiarEstado($paqueteId, $organizacionId, $nuevoEstado);

        $this->auditoriaRepo->registrar(
            contexto: $contexto,
            modulo: 'catalogo',
            accion: 'CAMBIAR_ESTADO_PAQUETE',
            entidadTipo: 'paquete',
            entidadId: (string) $paqueteId,
            datosPrevios: $previos,
            datosNuevos: ['estado' => $nuevoEstado->value]
        );

        return $ok;
    }

    /**
     * Sincroniza la composición de un paquete con validación anti-IDOR estricta.
     * @param array<int, array{item_comercial_id: int, cantidad: float, orden?: int}> $itemsDef
     */
    public function sincronizarComposicionPaquete(
        int $organizacionId,
        int $paqueteId,
        array $itemsDef,
        ContextoOperacion $contexto
    ): Paquete {
        $this->validarPermiso('catalogo.paquetes.gestionar', $contexto);
        $this->validarTenantContexto($organizacionId, $contexto);

        $paquete = $this->paqueteRepo->buscarPorId($paqueteId, $organizacionId);
        if ($paquete === null) {
            throw new InvalidArgumentException("Paquete no encontrado o no pertenece a la organización.");
        }

        $itemsVistos = [];
        $itemsValidados = [];

        foreach ($itemsDef as $index => $def) {
            $itemId = (int) ($def['item_comercial_id'] ?? 0);
            $cantidad = (float) ($def['cantidad'] ?? 0.0);
            $orden = (int) ($def['orden'] ?? $index);

            if ($itemId <= 0) {
                throw new InvalidArgumentException("Cada ítem del paquete debe contener un ID de ítem comercial válido.");
            }

            if ($cantidad <= 0.0) {
                throw new InvalidArgumentException("La cantidad para el ítem ID {$itemId} debe ser mayor a 0 (recibido: {$cantidad}).");
            }

            if (isset($itemsVistos[$itemId])) {
                throw new InvalidArgumentException("El ítem ID {$itemId} está duplicado en la composición del paquete.");
            }
            $itemsVistos[$itemId] = true;

            // Anti-IDOR: verificar que el ítem pertenece a la misma organización
            $item = $this->itemRepo->buscarPorId($itemId, $organizacionId);
            if ($item === null) {
                throw new InvalidArgumentException("El ítem ID {$itemId} no existe o no pertenece a la misma organización que el paquete.");
            }

            $itemsValidados[] = [
                'item_comercial_id' => $itemId,
                'cantidad' => $cantidad,
                'orden' => $orden,
            ];
        }

        $previos = $paquete->toArray();

        $transaccionIniciada = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $transaccionIniciada = true;
        }

        try {
            $this->paqueteRepo->sincronizarComposicion($paqueteId, $itemsValidados);

            $this->auditoriaRepo->registrar(
                contexto: $contexto,
                modulo: 'catalogo',
                accion: 'MODIFICAR_COMPOSICION_PAQUETE',
                entidadTipo: 'paquete',
                entidadId: (string) $paqueteId,
                datosPrevios: $previos,
                datosNuevos: ['items_composicion' => $itemsValidados]
            );

            if ($transaccionIniciada) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($transaccionIniciada && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $this->paqueteRepo->buscarPorId($paqueteId, $organizacionId);
    }

    // =========================================================================
    // 4. OFERTAS POR EDICIÓN FOLCLÓRICA
    // =========================================================================

    public function habilitarOfertaItem(
        int $organizacionId,
        int $edicionId,
        int $itemComercialId,
        ?int $capacidadReferencial,
        ContextoOperacion $contexto
    ): OfertaItemEdicion {
        $this->validarPermiso('catalogo.ofertas.gestionar', $contexto);
        $this->validarTenantContexto($organizacionId, $contexto);

        // Anti-IDOR: Edición e Ítem del mismo tenant
        $this->validarEdicionTenant($edicionId, $organizacionId);

        $item = $this->itemRepo->buscarPorId($itemComercialId, $organizacionId);
        if ($item === null) {
            throw new InvalidArgumentException("El ítem comercial no existe o no pertenece a la organización.");
        }

        $existente = $this->ofertaItemRepo->buscarPorItemYEdicion($itemComercialId, $edicionId, $organizacionId);
        if ($existente !== null) {
            throw new InvalidArgumentException("El ítem comercial ya se encuentra ofertado en esta edición.");
        }

        if ($capacidadReferencial !== null && $capacidadReferencial < 0) {
            throw new InvalidArgumentException("La capacidad referencial no puede ser negativa.");
        }

        $oferta = new OfertaItemEdicion(
            id: null,
            organizacionId: $organizacionId,
            edicionId: $edicionId,
            itemComercialId: $itemComercialId,
            estado: EstadoCatalogo::ACTIVO,
            capacidadReferencial: $capacidadReferencial
        );

        $creada = $this->ofertaItemRepo->guardar($oferta);

        $this->auditoriaRepo->registrar(
            contexto: $contexto,
            modulo: 'catalogo',
            accion: 'HABILITAR_OFERTA_ITEM_EDICION',
            entidadTipo: 'oferta_item_edicion',
            entidadId: (string) $creada->id,
            datosPrevios: null,
            datosNuevos: $creada->toArray()
        );

        return $creada;
    }

    public function habilitarOfertaPaquete(
        int $organizacionId,
        int $edicionId,
        int $paqueteId,
        ?int $capacidadReferencial,
        ContextoOperacion $contexto
    ): OfertaPaqueteEdicion {
        $this->validarPermiso('catalogo.ofertas.gestionar', $contexto);
        $this->validarTenantContexto($organizacionId, $contexto);

        // Anti-IDOR: Edición y Paquete del mismo tenant
        $this->validarEdicionTenant($edicionId, $organizacionId);

        $paquete = $this->paqueteRepo->buscarPorId($paqueteId, $organizacionId);
        if ($paquete === null) {
            throw new InvalidArgumentException("El paquete comercial no existe o no pertenece a la organización.");
        }

        $existente = $this->ofertaPaqueteRepo->buscarPorPaqueteYEdicion($paqueteId, $edicionId, $organizacionId);
        if ($existente !== null) {
            throw new InvalidArgumentException("El paquete comercial ya se encuentra ofertado en esta edición.");
        }

        if ($capacidadReferencial !== null && $capacidadReferencial < 0) {
            throw new InvalidArgumentException("La capacidad referencial no puede ser negativa.");
        }

        $oferta = new OfertaPaqueteEdicion(
            id: null,
            organizacionId: $organizacionId,
            edicionId: $edicionId,
            paqueteId: $paqueteId,
            estado: EstadoCatalogo::ACTIVO,
            capacidadReferencial: $capacidadReferencial
        );

        $creada = $this->ofertaPaqueteRepo->guardar($oferta);

        $this->auditoriaRepo->registrar(
            contexto: $contexto,
            modulo: 'catalogo',
            accion: 'HABILITAR_OFERTA_PAQUETE_EDICION',
            entidadTipo: 'oferta_paquete_edicion',
            entidadId: (string) $creada->id,
            datosPrevios: null,
            datosNuevos: $creada->toArray()
        );

        return $creada;
    }

    public function cambiarEstadoOfertaItem(
        int $organizacionId,
        int $ofertaItemId,
        EstadoCatalogo $nuevoEstado,
        ContextoOperacion $contexto
    ): bool {
        $this->validarPermiso('catalogo.ofertas.gestionar', $contexto);
        $this->validarTenantContexto($organizacionId, $contexto);

        $oferta = $this->ofertaItemRepo->buscarPorId($ofertaItemId, $organizacionId);
        if ($oferta === null) {
            throw new InvalidArgumentException("Oferta de ítem no encontrada o no pertenece a la organización.");
        }

        $previos = $oferta->toArray();
        $ok = $this->ofertaItemRepo->cambiarEstado($ofertaItemId, $organizacionId, $nuevoEstado);

        $this->auditoriaRepo->registrar(
            contexto: $contexto,
            modulo: 'catalogo',
            accion: 'CAMBIAR_ESTADO_OFERTA_ITEM_EDICION',
            entidadTipo: 'oferta_item_edicion',
            entidadId: (string) $ofertaItemId,
            datosPrevios: $previos,
            datosNuevos: ['estado' => $nuevoEstado->value]
        );

        return $ok;
    }

    public function cambiarEstadoOfertaPaquete(
        int $organizacionId,
        int $ofertaPaqueteId,
        EstadoCatalogo $nuevoEstado,
        ContextoOperacion $contexto
    ): bool {
        $this->validarPermiso('catalogo.ofertas.gestionar', $contexto);
        $this->validarTenantContexto($organizacionId, $contexto);

        $oferta = $this->ofertaPaqueteRepo->buscarPorId($ofertaPaqueteId, $organizacionId);
        if ($oferta === null) {
            throw new InvalidArgumentException("Oferta de paquete no encontrada o no pertenece a la organización.");
        }

        $previos = $oferta->toArray();
        $ok = $this->ofertaPaqueteRepo->cambiarEstado($ofertaPaqueteId, $organizacionId, $nuevoEstado);

        $this->auditoriaRepo->registrar(
            contexto: $contexto,
            modulo: 'catalogo',
            accion: 'CAMBIAR_ESTADO_OFERTA_PAQUETE_EDICION',
            entidadTipo: 'oferta_paquete_edicion',
            entidadId: (string) $ofertaPaqueteId,
            datosPrevios: $previos,
            datosNuevos: ['estado' => $nuevoEstado->value]
        );

        return $ok;
    }

    // =========================================================================
    // 5. TARIFAS Y PRECIOS SOBERANOS POR EDICIÓN (CONCURRENCIA + HISTORIAL)
    // =========================================================================

    public function fijarTarifaInicialItem(
        int $organizacionId,
        int $ofertaItemId,
        float $precio,
        ContextoOperacion $contexto
    ): TarifaItemEdicion {
        $this->validarPermiso('catalogo.tarifas.gestionar', $contexto);
        $this->validarTenantContexto($organizacionId, $contexto);

        $oferta = $this->ofertaItemRepo->buscarPorId($ofertaItemId, $organizacionId);
        if ($oferta === null) {
            throw new InvalidArgumentException("Oferta de ítem no encontrada o no pertenece a la organización.");
        }

        if ($precio < 0.0) {
            throw new InvalidArgumentException("El precio de la tarifa no puede ser negativo (recibido: {$precio}).");
        }

        // Moneda soberana resuelta desde plataforma (Fail-Closed)
        $moneda = $this->resolverMonedaSoberana();

        $existente = $this->tarifaItemRepo->buscarPorOfertaId($ofertaItemId);
        if ($existente !== null) {
            throw new InvalidArgumentException("La oferta de ítem ya cuenta con una tarifa vigente asignada. Use actualizarTarifaItem para modificarla.");
        }

        $tarifa = new TarifaItemEdicion(
            id: null,
            ofertaItemId: $ofertaItemId,
            moneda: $moneda,
            precio: $precio,
            versionBloqueo: 1
        );

        $creada = $this->tarifaItemRepo->guardar($tarifa);

        $this->auditoriaRepo->registrar(
            contexto: $contexto,
            modulo: 'catalogo',
            accion: 'CREAR_TARIFA_ITEM',
            entidadTipo: 'tarifa_item_edicion',
            entidadId: (string) $creada->id,
            datosPrevios: null,
            datosNuevos: $creada->toArray()
        );

        return $creada;
    }

    public function actualizarTarifaItem(
        int $organizacionId,
        int $tarifaId,
        float $nuevoPrecio,
        int $versionEsperada,
        ?string $motivo,
        ContextoOperacion $contexto
    ): TarifaItemEdicion {
        $this->validarPermiso('catalogo.tarifas.gestionar', $contexto);
        $this->validarTenantContexto($organizacionId, $contexto);

        $tarifa = $this->tarifaItemRepo->buscarPorId($tarifaId);
        if ($tarifa === null) {
            throw new InvalidArgumentException("Tarifa de ítem no encontrada.");
        }

        // Validar tenant a través de la oferta
        $oferta = $this->ofertaItemRepo->buscarPorId($tarifa->ofertaItemId, $organizacionId);
        if ($oferta === null) {
            throw new AccesoDenegadoExcepcion("La tarifa no pertenece a la organización solicitante.");
        }

        if ($nuevoPrecio < 0.0) {
            throw new InvalidArgumentException("El precio de la tarifa no puede ser negativo.");
        }

        // Optimistic locking previo
        if ($tarifa->versionBloqueo !== $versionEsperada) {
            throw new ConflictoConcurrenciaExcepcion(
                "La tarifa fue modificada concurrentemente por otro usuario. Recargue los datos para continuar."
            );
        }

        $previos = $tarifa->toArray();

        $transaccionIniciada = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $transaccionIniciada = true;
        }

        try {
            $actualizado = $this->tarifaItemRepo->actualizarPrecio($tarifaId, $organizacionId, $nuevoPrecio, $versionEsperada);
            if (!$actualizado) {
                throw new ConflictoConcurrenciaExcepcion(
                    "Conflicto de concurrencia: la versión esperada ({$versionEsperada}) ya no coincide en la base de datos."
                );
            }

            // Registro inmutable en historial append-only
            $historial = new HistorialTarifaItem(
                id: null,
                tarifaItemId: $tarifaId,
                precioAnterior: $tarifa->precio,
                precioNuevo: $nuevoPrecio,
                moneda: $tarifa->moneda,
                motivo: $motivo ? trim($motivo) : null,
                actorTipo: $contexto->usuarioId !== null ? 'HUMANO' : 'SISTEMA',
                usuarioId: $contexto->usuarioId,
                actorSistemaId: $contexto->actorSistemaId,
                canalId: $contexto->canalId,
                correlacionId: $contexto->correlacionId
            );
            $this->historialRepo->registrarItem($historial);

            $this->auditoriaRepo->registrar(
                contexto: $contexto,
                modulo: 'catalogo',
                accion: 'CAMBIAR_TARIFA_ITEM',
                entidadTipo: 'tarifa_item_edicion',
                entidadId: (string) $tarifaId,
                datosPrevios: $previos,
                datosNuevos: [
                    'precio' => $nuevoPrecio,
                    'motivo' => $motivo,
                    'version_bloqueo' => $versionEsperada + 1
                ]
            );

            if ($transaccionIniciada) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($transaccionIniciada && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $this->tarifaItemRepo->buscarPorId($tarifaId);
    }

    public function fijarTarifaInicialPaquete(
        int $organizacionId,
        int $ofertaPaqueteId,
        float $precio,
        ContextoOperacion $contexto
    ): TarifaPaqueteEdicion {
        $this->validarPermiso('catalogo.tarifas.gestionar', $contexto);
        $this->validarTenantContexto($organizacionId, $contexto);

        $oferta = $this->ofertaPaqueteRepo->buscarPorId($ofertaPaqueteId, $organizacionId);
        if ($oferta === null) {
            throw new InvalidArgumentException("Oferta de paquete no encontrada o no pertenece a la organización.");
        }

        if ($precio < 0.0) {
            throw new InvalidArgumentException("El precio de la tarifa no puede ser negativo (recibido: {$precio}).");
        }

        $moneda = $this->resolverMonedaSoberana();

        $existente = $this->tarifaPaqueteRepo->buscarPorOfertaId($ofertaPaqueteId);
        if ($existente !== null) {
            throw new InvalidArgumentException("La oferta de paquete ya cuenta con una tarifa vigente asignada. Use actualizarTarifaPaquete para modificarla.");
        }

        $tarifa = new TarifaPaqueteEdicion(
            id: null,
            ofertaPaqueteId: $ofertaPaqueteId,
            moneda: $moneda,
            precio: $precio,
            versionBloqueo: 1
        );

        $creada = $this->tarifaPaqueteRepo->guardar($tarifa);

        $this->auditoriaRepo->registrar(
            contexto: $contexto,
            modulo: 'catalogo',
            accion: 'CREAR_TARIFA_PAQUETE',
            entidadTipo: 'tarifa_paquete_edicion',
            entidadId: (string) $creada->id,
            datosPrevios: null,
            datosNuevos: $creada->toArray()
        );

        return $creada;
    }

    public function actualizarTarifaPaquete(
        int $organizacionId,
        int $tarifaId,
        float $nuevoPrecio,
        int $versionEsperada,
        ?string $motivo,
        ContextoOperacion $contexto
    ): TarifaPaqueteEdicion {
        $this->validarPermiso('catalogo.tarifas.gestionar', $contexto);
        $this->validarTenantContexto($organizacionId, $contexto);

        $tarifa = $this->tarifaPaqueteRepo->buscarPorId($tarifaId);
        if ($tarifa === null) {
            throw new InvalidArgumentException("Tarifa de paquete no encontrada.");
        }

        $oferta = $this->ofertaPaqueteRepo->buscarPorId($tarifa->ofertaPaqueteId, $organizacionId);
        if ($oferta === null) {
            throw new AccesoDenegadoExcepcion("La tarifa no pertenece a la organización solicitante.");
        }

        if ($nuevoPrecio < 0.0) {
            throw new InvalidArgumentException("El precio de la tarifa no puede ser negativo.");
        }

        if ($tarifa->versionBloqueo !== $versionEsperada) {
            throw new ConflictoConcurrenciaExcepcion(
                "La tarifa fue modificada concurrentemente por otro usuario. Recargue los datos para continuar."
            );
        }

        $previos = $tarifa->toArray();

        $transaccionIniciada = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $transaccionIniciada = true;
        }

        try {
            $actualizado = $this->tarifaPaqueteRepo->actualizarPrecio($tarifaId, $organizacionId, $nuevoPrecio, $versionEsperada);
            if (!$actualizado) {
                throw new ConflictoConcurrenciaExcepcion(
                    "Conflicto de concurrencia: la versión esperada ({$versionEsperada}) ya no coincide en la base de datos."
                );
            }

            $historial = new HistorialTarifaPaquete(
                id: null,
                tarifaPaqueteId: $tarifaId,
                precioAnterior: $tarifa->precio,
                precioNuevo: $nuevoPrecio,
                moneda: $tarifa->moneda,
                motivo: $motivo ? trim($motivo) : null,
                actorTipo: $contexto->usuarioId !== null ? 'HUMANO' : 'SISTEMA',
                usuarioId: $contexto->usuarioId,
                actorSistemaId: $contexto->actorSistemaId,
                canalId: $contexto->canalId,
                correlacionId: $contexto->correlacionId
            );
            $this->historialRepo->registrarPaquete($historial);

            $this->auditoriaRepo->registrar(
                contexto: $contexto,
                modulo: 'catalogo',
                accion: 'CAMBIAR_TARIFA_PAQUETE',
                entidadTipo: 'tarifa_paquete_edicion',
                entidadId: (string) $tarifaId,
                datosPrevios: $previos,
                datosNuevos: [
                    'precio' => $nuevoPrecio,
                    'motivo' => $motivo,
                    'version_bloqueo' => $versionEsperada + 1
                ]
            );

            if ($transaccionIniciada) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($transaccionIniciada && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $this->tarifaPaqueteRepo->buscarPorId($tarifaId);
    }

    // =========================================================================
    // 7. OPERACIONES DE CONSULTA Y LECTURA SOBERANA
    // =========================================================================

    public function obtenerCategoria(int $organizacionId, int $categoriaId, ContextoOperacion $contexto): ?CategoriaItem
    {
        $this->validarPermiso('catalogo.ver', $contexto);
        $this->validarTenantContexto($organizacionId, $contexto);

        return $this->categoriaRepo->buscarPorId($categoriaId, $organizacionId);
    }

    /**
     * @return CategoriaItem[]
     */
    public function listarCategorias(int $organizacionId, ?EstadoCatalogo $estado, ContextoOperacion $contexto): array
    {
        $this->validarPermiso('catalogo.ver', $contexto);
        $this->validarTenantContexto($organizacionId, $contexto);

        return $this->categoriaRepo->listar($organizacionId, $estado);
    }

    public function obtenerItem(int $organizacionId, int $itemId, ContextoOperacion $contexto): ?ItemComercial
    {
        $this->validarPermiso('catalogo.ver', $contexto);
        $this->validarTenantContexto($organizacionId, $contexto);

        return $this->itemRepo->buscarPorId($itemId, $organizacionId);
    }

    /**
     * @return ItemComercial[]
     */
    public function listarItems(
        int $organizacionId,
        ?int $categoriaId,
        ?TipoItemComercial $tipo,
        ?EstadoCatalogo $estado,
        ContextoOperacion $contexto,
        ?string $busqueda = null
    ): array {
        $this->validarPermiso('catalogo.ver', $contexto);
        $this->validarTenantContexto($organizacionId, $contexto);

        return $this->itemRepo->listar($organizacionId, $categoriaId, $tipo, $estado, $busqueda);
    }

    public function obtenerPaquete(int $organizacionId, int $paqueteId, ContextoOperacion $contexto): ?Paquete
    {
        $this->validarPermiso('catalogo.ver', $contexto);
        $this->validarTenantContexto($organizacionId, $contexto);

        return $this->paqueteRepo->buscarPorId($paqueteId, $organizacionId);
    }

    /**
     * @return Paquete[]
     */
    public function listarPaquetes(
        int $organizacionId,
        ?EstadoCatalogo $estado,
        ContextoOperacion $contexto,
        ?string $busqueda = null
    ): array {
        $this->validarPermiso('catalogo.ver', $contexto);
        $this->validarTenantContexto($organizacionId, $contexto);

        return $this->paqueteRepo->listar($organizacionId, $estado, $busqueda);
    }

    public function obtenerOfertaItem(int $organizacionId, int $ofertaId, ContextoOperacion $contexto): ?OfertaItemEdicion
    {
        $this->validarPermiso('catalogo.ver', $contexto);
        $this->validarTenantContexto($organizacionId, $contexto);

        return $this->ofertaItemRepo->buscarPorId($ofertaId, $organizacionId);
    }

    /**
     * @return OfertaItemEdicion[]
     */
    public function listarOfertasItemsPorEdicion(
        int $organizacionId,
        int $edicionId,
        ?EstadoCatalogo $estado,
        ContextoOperacion $contexto
    ): array {
        $this->validarPermiso('catalogo.ver', $contexto);
        $this->validarTenantContexto($organizacionId, $contexto);
        $this->validarEdicionTenant($edicionId, $organizacionId);

        return $this->ofertaItemRepo->listarPorEdicion($organizacionId, $edicionId, $estado);
    }

    public function obtenerOfertaPaquete(int $organizacionId, int $ofertaId, ContextoOperacion $contexto): ?OfertaPaqueteEdicion
    {
        $this->validarPermiso('catalogo.ver', $contexto);
        $this->validarTenantContexto($organizacionId, $contexto);

        return $this->ofertaPaqueteRepo->buscarPorId($ofertaId, $organizacionId);
    }

    /**
     * @return OfertaPaqueteEdicion[]
     */
    public function listarOfertasPaquetesPorEdicion(
        int $organizacionId,
        int $edicionId,
        ?EstadoCatalogo $estado,
        ContextoOperacion $contexto
    ): array {
        $this->validarPermiso('catalogo.ver', $contexto);
        $this->validarTenantContexto($organizacionId, $contexto);
        $this->validarEdicionTenant($edicionId, $organizacionId);

        return $this->ofertaPaqueteRepo->listarPorEdicion($organizacionId, $edicionId, $estado);
    }

    public function obtenerTarifaVigenteItem(int $organizacionId, int $ofertaItemId, ContextoOperacion $contexto): ?TarifaItemEdicion
    {
        $this->validarPermiso('catalogo.ver', $contexto);
        $this->validarTenantContexto($organizacionId, $contexto);

        $oferta = $this->ofertaItemRepo->buscarPorId($ofertaItemId, $organizacionId);
        if ($oferta === null) {
            return null;
        }

        return $this->tarifaItemRepo->buscarPorOfertaId($ofertaItemId);
    }

    public function obtenerTarifaVigentePaquete(int $organizacionId, int $ofertaPaqueteId, ContextoOperacion $contexto): ?TarifaPaqueteEdicion
    {
        $this->validarPermiso('catalogo.ver', $contexto);
        $this->validarTenantContexto($organizacionId, $contexto);

        $oferta = $this->ofertaPaqueteRepo->buscarPorId($ofertaPaqueteId, $organizacionId);
        if ($oferta === null) {
            return null;
        }

        return $this->tarifaPaqueteRepo->buscarPorOfertaId($ofertaPaqueteId);
    }

    /**
     * Consulta el historial append-only de tarifas de ítem.
     * Requiere exclusivamente el permiso granular 'catalogo.tarifas.ver_historial'.
     *
     * @return HistorialTarifaItem[]
     */
    public function listarHistorialTarifasItem(int $organizacionId, int $tarifaItemId, ContextoOperacion $contexto): array
    {
        $this->validarPermiso('catalogo.tarifas.ver_historial', $contexto);
        $this->validarTenantContexto($organizacionId, $contexto);

        $tarifa = $this->tarifaItemRepo->buscarPorId($tarifaItemId);
        if ($tarifa === null) {
            return [];
        }

        $oferta = $this->ofertaItemRepo->buscarPorId($tarifa->ofertaItemId, $organizacionId);
        if ($oferta === null) {
            throw new AccesoDenegadoExcepcion("La tarifa solicitada no pertenece a la organización solicitante.");
        }

        return $this->historialRepo->listarPorTarifaItem($tarifaItemId);
    }

    /**
     * Consulta el historial append-only de tarifas de paquete.
     * Requiere exclusivamente el permiso granular 'catalogo.tarifas.ver_historial'.
     *
     * @return HistorialTarifaPaquete[]
     */
    public function listarHistorialTarifasPaquete(int $organizacionId, int $tarifaPaqueteId, ContextoOperacion $contexto): array
    {
        $this->validarPermiso('catalogo.tarifas.ver_historial', $contexto);
        $this->validarTenantContexto($organizacionId, $contexto);

        $tarifa = $this->tarifaPaqueteRepo->buscarPorId($tarifaPaqueteId);
        if ($tarifa === null) {
            return [];
        }

        $oferta = $this->ofertaPaqueteRepo->buscarPorId($tarifa->ofertaPaqueteId, $organizacionId);
        if ($oferta === null) {
            throw new AccesoDenegadoExcepcion("La tarifa solicitada no pertenece a la organización solicitante.");
        }

        return $this->historialRepo->listarPorTarifaPaquete($tarifaPaqueteId);
    }

    // =========================================================================
    // VALIDACIONES Y MECANISMOS PRIVADOS DE GOBERNANZA
    // =========================================================================

    private function resolverMonedaSoberana(): string
    {
        $moneda = $this->configServicio->obtenerPlataforma('plataforma.moneda_principal');
        if (!is_string($moneda) || trim($moneda) === '') {
            throw new RuntimeException("Configuración institucional crítica ausente: 'plataforma.moneda_principal' no está configurada.");
        }

        $monedaLimpia = strtoupper(trim($moneda));
        if (!preg_match('/^[A-Z]{3}$/', $monedaLimpia)) {
            throw new RuntimeException("La moneda institucional '{$moneda}' no es un código ISO 4217 válido de 3 caracteres.");
        }

        return $monedaLimpia;
    }

    private function validarPermiso(string $permiso, ContextoOperacion $contexto): void
    {
        if ($contexto->usuarioId === null) {
            // Operación técnica del sistema permitida
            return;
        }

        if (!$this->authzServicio->tienePermiso($contexto->usuarioId, $permiso)) {
            throw new AccesoDenegadoExcepcion(
                "El usuario no cuenta con el permiso requerido '{$permiso}' para operar el catálogo comercial."
            );
        }
    }

    private function validarTenantContexto(int $organizacionId, ContextoOperacion $contexto): void
    {
        if ($contexto->organizacionId !== null && $contexto->organizacionId !== $organizacionId) {
            throw new AccesoDenegadoExcepcion(
                "Inconsistencia multi-tenant: la organización solicitada ({$organizacionId}) no coincide con el contexto ({$contexto->organizacionId})."
            );
        }
    }

    private function validarEdicionTenant(int $edicionId, int $organizacionId): void
    {
        $edicion = $this->edicionRepo->buscarPorId($edicionId);
        if ($edicion === null || $edicion->organizacionId !== $organizacionId) {
            throw new InvalidArgumentException(
                "La edición especificada (ID {$edicionId}) no existe o pertenece a otra organización."
            );
        }
    }
}
