<?php

declare(strict_types=1);

namespace Aplicacion\Configuracion;

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Entidades\ParametroConfiguracion;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\ConfiguracionRepositorio;
use Aplicacion\Repositorios\OrganizacionRepositorio;
use InvalidArgumentException;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use PDO;

/**
 * Servicio Central de Configuración Soberana de CandelariaAPP.
 * Implementa la separación estricta de tres ámbitos:
 *   1. PLATAFORMA (Parámetros globales soberanos administrados exclusivamente por Superadmin)
 *   2. ORGANIZACIÓN (Parámetros operativos del tenant)
 *   3. EDICIÓN (Reservado arquitectónicamente para fases posteriores)
 *
 * Ofrece acceso centralizado, tipado estricto, validación de reglas,
 * caché en memoria de proceso y auditoría inmutable libre de secretos.
 */
class ConfiguracionServicio
{
    private PDO $pdo;
    private ConfiguracionRepositorio $configRepo;
    private OrganizacionRepositorio $orgRepo;
    private AutorizacionServicio $authzServicio;
    private AuditoriaRepositorio $auditoriaRepo;

    /**
     * Memoria de proceso para caché de lecturas frecuentes.
     * @var array<string, mixed>
     */
    private array $cache = [];

    public function __construct(
        ?ConfiguracionRepositorio $configRepo = null,
        ?OrganizacionRepositorio $orgRepo = null,
        ?AutorizacionServicio $authzServicio = null,
        ?AuditoriaRepositorio $auditoriaRepo = null,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
        $this->configRepo = $configRepo ?? new ConfiguracionRepositorio($this->pdo);
        $this->orgRepo = $orgRepo ?? new OrganizacionRepositorio($this->pdo);
        $this->authzServicio = $authzServicio ?? new AutorizacionServicio(pdo: $this->pdo);
        $this->auditoriaRepo = $auditoriaRepo ?? new AuditoriaRepositorio($this->pdo);
    }

    /**
     * Obtiene un parámetro tipado del ámbito soberano PLATAFORMA.
     */
    public function obtenerPlataforma(string $codigo, mixed $defecto = null): mixed
    {
        $claveCache = 'plataforma:' . trim($codigo);
        if (array_key_exists($claveCache, $this->cache)) {
            return $this->cache[$claveCache];
        }

        $param = $this->configRepo->buscarParametro($codigo, null);
        if ($param === null || $param->estado !== 'ACTIVO') {
            return $defecto;
        }

        $valorCasteado = $param->obtenerValorCasteado();
        $this->cache[$claveCache] = $valorCasteado;

        return $valorCasteado;
    }

    /**
     * Obtiene un parámetro tipado del ámbito ORGANIZACION.
     */
    public function obtenerOrganizacion(int $organizacionId, string $codigo, mixed $defecto = null): mixed
    {
        $claveCache = "org:{$organizacionId}:" . trim($codigo);
        if (array_key_exists($claveCache, $this->cache)) {
            return $this->cache[$claveCache];
        }

        $param = $this->configRepo->buscarParametro($codigo, $organizacionId);
        if ($param === null || $param->estado !== 'ACTIVO') {
            return $defecto;
        }

        $valorCasteado = $param->obtenerValorCasteado();
        $this->cache[$claveCache] = $valorCasteado;

        return $valorCasteado;
    }

    /**
     * Actualiza un parámetro soberano de PLATAFORMA.
     * Gobernanza: Exclusivo para Superadministrador de Plataforma.
     *
     * @throws AccesoDenegadoExcepcion
     * @throws InvalidArgumentException
     */
    public function actualizarPlataforma(
        string $codigo,
        mixed $nuevoValor,
        int $operadorId,
        ContextoOperacion $contexto
    ): void {
        // 1. Autorización: Exclusivo para Superadmin de Plataforma
        if (!$this->authzServicio->esSuperadmin($operadorId)) {
            throw new AccesoDenegadoExcepcion(
                'Acceso denegado: solo el Superadministrador de Plataforma puede modificar parámetros soberanos del sistema.'
            );
        }

        // 2. Parámetro debe existir en el catálogo gobernado
        $param = $this->configRepo->buscarParametro($codigo, null);
        if ($param === null) {
            throw new InvalidArgumentException(
                "El parámetro de plataforma '{$codigo}' no existe en el catálogo gobernado."
            );
        }

        if (!$param->esEditable) {
            throw new InvalidArgumentException(
                "El parámetro de plataforma '{$codigo}' está protegido y no admite modificaciones en tiempo de ejecución."
            );
        }

        // 3. Validar tipo y reglas
        $valorString = $this->formatearYValidarValor($param->tipoDato, $nuevoValor, $param->reglasValidacion);

        $valorPrevio = $param->valor;

        // 4. Persistir
        $this->configRepo->actualizarValor($codigo, null, $valorString);

        // 5. Invalidar caché
        unset($this->cache['plataforma:' . trim($codigo)]);

        // 6. Auditar cambio sin secretos
        $this->auditoriaRepo->registrar(
            contexto: $contexto,
            modulo: 'configuracion',
            accion: 'ACTUALIZAR_CONFIGURACION_PLATAFORMA',
            entidadTipo: 'PARAMETRO_CONFIGURACION',
            entidadId: $codigo,
            datosPrevios: ['valor' => $valorPrevio],
            datosNuevos: ['valor' => $valorString]
        );
    }

    /**
     * Actualiza un parámetro del ámbito ORGANIZACION.
     * Gobernanza: Requiere pertenencia al tenant y permiso configuracion_organizacion.editar.
     *
     * @throws AccesoDenegadoExcepcion
     * @throws InvalidArgumentException
     */
    public function actualizarOrganizacion(
        int $organizacionId,
        string $codigo,
        mixed $nuevoValor,
        int $operadorId,
        ContextoOperacion $contexto
    ): void {
        // 1. Aislamiento Anti-IDOR
        if (!$this->authzServicio->verificarAlcanceOrganizacion($operadorId, $organizacionId)) {
            throw new AccesoDenegadoExcepcion(
                'Acceso denegado: el operador no cuenta con alcance sobre la organización especificada.'
            );
        }

        // 2. Permiso RBAC
        if (!$this->authzServicio->tienePermiso($operadorId, 'configuracion_organizacion.editar')) {
            throw new AccesoDenegadoExcepcion(
                "Acceso denegado: se requiere el permiso 'configuracion_organizacion.editar'."
            );
        }

        // 3. Parámetro debe existir en catálogo gobernado del tenant
        $param = $this->configRepo->buscarParametro($codigo, $organizacionId);
        if ($param === null) {
            throw new InvalidArgumentException(
                "El parámetro '{$codigo}' no existe en el catálogo gobernado para la organización {$organizacionId}."
            );
        }

        if (!$param->esEditable) {
            throw new InvalidArgumentException(
                "El parámetro '{$codigo}' está protegido y no admite modificaciones en tiempo de ejecución."
            );
        }

        // 4. Validar tipo y reglas
        $valorString = $this->formatearYValidarValor($param->tipoDato, $nuevoValor, $param->reglasValidacion);

        $valorPrevio = $param->valor;

        // 5. Persistir
        $this->configRepo->actualizarValor($codigo, $organizacionId, $valorString);

        // 6. Invalidar caché
        unset($this->cache["org:{$organizacionId}:" . trim($codigo)]);

        // 7. Auditar cambio sin secretos
        $this->auditoriaRepo->registrar(
            contexto: $contexto,
            modulo: 'configuracion',
            accion: 'ACTUALIZAR_CONFIGURACION_ORGANIZACION',
            entidadTipo: 'PARAMETRO_CONFIGURACION',
            entidadId: "{$organizacionId}:{$codigo}",
            datosPrevios: ['valor' => $valorPrevio],
            datosNuevos: ['valor' => $valorString]
        );
    }

    /**
     * Valida y formatea el valor a cadena antes de su persistencia.
     */
    private function formatearYValidarValor(string $tipoDato, mixed $valor, ?array $reglas): string
    {
        if (is_bool($valor)) {
            $valorStr = $valor ? '1' : '0';
        } elseif (is_int($valor) || is_float($valor)) {
            $valorStr = (string) $valor;
        } elseif (is_string($valor)) {
            $valorStr = trim($valor);
        } else {
            throw new InvalidArgumentException('Tipo de valor no soportado para configuración.');
        }

        // Validación estricta conforme a la entidad
        ParametroConfiguracion::validarValorParaTipo($tipoDato, $valorStr, $reglas);

        return $valorStr;
    }

    /**
     * Invalida selectiva o totalmente la caché en memoria.
     */
    public function limpiarCache(?string $clave = null): void
    {
        if ($clave !== null) {
            unset($this->cache[$clave]);
        } else {
            $this->cache = [];
        }
    }
}
