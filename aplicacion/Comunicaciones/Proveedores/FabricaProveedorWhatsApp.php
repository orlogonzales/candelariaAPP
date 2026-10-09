<?php

declare(strict_types=1);

namespace Aplicacion\Comunicaciones\Proveedores;

use InvalidArgumentException;

class FabricaProveedorWhatsApp
{
    /**
     * @var array<string, WhatsAppProveedorInterface>
     */
    private array $adaptadores = [];

    public function __construct()
    {
        $this->registrar(new SimuladorWhatsAppAdaptador());
        $this->registrar(new MetaCloudApiAdaptador());
    }

    public function registrar(WhatsAppProveedorInterface $adaptador): void
    {
        $this->adaptadores[$adaptador->obtenerCodigo()] = $adaptador;
    }

    public function obtenerPorCodigo(string $codigo): WhatsAppProveedorInterface
    {
        $codigoNorm = strtoupper(trim($codigo));
        if (!isset($this->adaptadores[$codigoNorm])) {
            throw new InvalidArgumentException("Proveedor de WhatsApp no soportado: '{$codigo}'");
        }

        return $this->adaptadores[$codigoNorm];
    }
}
