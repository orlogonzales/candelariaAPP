# CANDELARIAAPP V1 — MANUAL DEL ADMINISTRADOR DEL SERVIDOR
## Instalación, Configuración, Operación, Respaldo y Recuperación (DRP)

**Versión:** 1.0.0 (Cierre y Congelamiento V1)  
**Directiva de Gobierno:** `DT-V1-CIERRE-001`  
**Estado:** V1 Certificada Localmente / APTO CON RESTRICCIONES  
**Audiencia:** Administradores de Sistemas (SysAdmin / DevOps), Ingenieros de Infraestructura y Soporte de Plataforma

---

> [!WARNING] Advertencia de Seguridad y Estado de Despliegue
> El presente manual describe las directivas de configuración técnica validadas en el entorno de desarrollo y pruebas de homologación. **El despliegue en el servidor de producción definitivo todavía no ha sido certificado ni auditado en vivo.** Ninguna pasarela de pago ni integración de WhatsApp debe operarse en modo de producción sin completar previamente el checklist individual de activación y recibir la autorización expresa de la Dirección Técnica.

---

## 1. Requisitos del Sistema y Pila Tecnológica

### 1.1 Entorno de Servidor (Linux / Unix Recomendado para Producción)
- **Sistema Operativo:** Ubuntu Server 22.04 LTS / 24.04 LTS o Debian 12
- **Servidor Web:** Apache 2.4.52+ (o Nginx 1.22+ como proxy inverso)
- **Módulos Apache Requeridos:**
  - `mod_rewrite` (imprescindible para el enrutamiento frontal hacia `/publico/index.php`)
  - `mod_headers` (para cabeceras de seguridad HSTS, X-Content-Type-Options, etc.)
  - `mod_ssl` (para terminación TLS 1.3 obligatoria)
- **Motor de Base de Datos:** MySQL 8.0.30+ (o MariaDB 10.11+) con soporte de `utf8mb4` y transacciones ACID en InnoDB.
- **Intérprete PHP:** PHP 8.3 (Versión oficial de referencia certificada para CandelariaAPP V1).

### 1.2 Extensiones PHP 8.3 Obligatorias
```bash
sudo apt-get install -y php8.3 php8.3-fpm php8.3-mysql php8.3-mbstring \
                        php8.3-xml php8.3-curl php8.3-zip php8.3-gd php8.3-bcmath
```

---

## 2. Instalación y Configuración del Servidor Web

### 2.1 Aislamiento de DocumentRoot (Defensa en Profundidad)
Por razones de seguridad estricta, la raíz pública del servidor web **debe apuntar exclusivamente a la carpeta `publico/`** del proyecto:

```apache
<VirtualHost *:443>
    ServerName app.candelaria.pe
    DocumentRoot /var/www/candelaria/publico

    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/app.candelaria.pe/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/app.candelaria.pe/privkey.pem
    SSLProtocol all -SSLv3 -TLSv1 -TLSv1.1

    <Directory /var/www/candelaria/publico>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted

        # Reescritura al controlador frontal
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^ index.php [QSA,L]
    </Directory>

    # Bloqueo total de carpetas internas si la raíz llegara a exponerse
    <Directory /var/www/candelaria/almacenamiento/privado>
        Require all denied
    </Directory>
    <Directory /var/www/candelaria/base_datos>
        Require all denied
    </Directory>
</VirtualHost>
```

### 2.2 Permisos de Archivos y Carpetas en el Servidor
```bash
# Propietario web
sudo chown -R www-data:www-data /var/www/candelaria

# Permisos generales de lectura/ejecución
sudo find /var/www/candelaria -type d -exec chmod 755 {} \;
sudo find /var/www/candelaria -type f -exec chmod 644 {} \;

# Permisos de escritura exclusivos para almacenamiento y logs
sudo chmod -R 775 /var/www/candelaria/almacenamiento
```

---

## 3. Variables de Entorno y Configuración (.env)

Copie la plantilla de producción y aplique permisos restrictivos:
```bash
cp .env.produccion.ejemplo .env
chmod 600 .env
```

Parámetros críticos que deben configurarse:
```ini
# Identificación de Entorno
APP_ENV=production
APP_DEPURACION=false
APP_URL=https://app.candelaria.pe

# Base de Datos de Producción
BD_HOST=127.0.0.1
BD_PUERTO=3306
BD_NOMBRE=app_candelaria_prod
BD_USUARIO=usr_candelaria
BD_CONTRASENA="CLAVE_ROBUSTA_GENERADA_CSPRNG"

# Sesiones Seguras
SESION_SEGURA=true
SESION_SAMESITE=Strict
SESION_HTTPONLY=true
SESION_LIFETIME=28800

# Integraciones Externas (Modo Sandbox Inicial Obligatorio)
CULQI_PUBLIC_KEY=pk_test_...
CULQI_SECRET_KEY=sk_test_...
NIUBIZ_ENV=sandbox
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
MERCADOPAGO_ACCESS_TOKEN=TEST-...
WHATSAPP_ADAPTADOR=SIMULADOR_SANDBOX
```

---

## 4. Tareas Programadas y Workers en Segundo Plano

Para garantizar la liberación de cupos y el despacho de mensajería, configure en `crontab -e -u www-data`:

```cron
# 1. Liberación atómica de bloqueos temporales de cupos con TTL (cada 2 minutos)
*/2 * * * * php /var/www/candelaria/consola/worker_bloqueos_temporales.php >> /var/www/candelaria/almacenamiento/logs/crons.log 2>&1

# 2. Despacho y conciliación de Outbox de WhatsApp (cada 1 minuto)
* * * * * php /var/www/candelaria/consola/worker_outbox_comunicaciones.php >> /var/www/candelaria/almacenamiento/logs/crons.log 2>&1

# 3. Rotación semanal de logs de aplicación (domingos a las 03:00 am)
0 3 * * 0 /usr/bin/find /var/www/candelaria/almacenamiento/logs/ -name "*.log" -size +50M -exec mv {} {}.$(date +\%Y\%m\%d) \;
```

---

## 5. Protocolo de Respaldo y Recuperación Ante Desastres (DRP)

### 5.1 Distinción Fundamental: Ensayos de Recuperación vs Política Continua
- **Ensayo de Recuperación (Acreditado en `f3ddb21`):** Demostró la capacidad técnica del software para restaurar ventas, pagos desglosados, recibos y archivos físicos en una base aislada verificando hashes criptográficos SHA-256 1:1.
- **Política Operativa Continua (Requerida en Producción):** Implica la automatización diaria de volcados, copia externa (off-site), cifrado en reposo y ensayos periódicos trimestrales de contingencia.

### 5.2 Rutina Diaria de Respaldo Automatizado
Ejecutar diariamente a las 02:00 AM mediante script institucional:
```bash
#!/usr/bin/env bash
FECHA=$(date +%Y%m%d_%H%M%S)
DESTINO="/respaldos/candelaria/${FECHA}"
mkdir -p "${DESTINO}"

# 1. Respaldo coherente de MySQL (InnoDB consistente)
mysqldump --single-transaction --quick --routines --triggers --events \
          -u usr_candelaria -p"CLAVE" app_candelaria_prod | gzip > "${DESTINO}/bd_${FECHA}.sql.gz"

# 2. Respaldo de archivos privados emitidos y multimedia
tar -czf "${DESTINO}/almacenamiento_${FECHA}.tar.gz" -C /var/www/candelaria/almacenamiento/privado .

# 3. Generación de Manifiesto Criptográfico SHA-256
cd "${DESTINO}" && sha256sum * > MANIFIESTO_HASHES.sha256

# 4. Envío cifrado a almacenamiento secundario (S3 / NAS remoto)
# rsync -avz "${DESTINO}" respaldo-remoto:/storage/candelaria/
```

### 5.3 Procedimiento de Restauración Ante Desastres
1. **Verificar Integridad del Respaldo:**
   ```bash
   cd /respaldos/candelaria/YYYYMMDD_HHMMSS
   sha256sum -c MANIFIESTO_HASHES.sha256
   ```
2. **Restaurar Base de Datos:**
   ```bash
   gunzip < bd_YYYYMMDD_HHMMSS.sql.gz | mysql -u usr_candelaria -p app_candelaria_prod
   ```
3. **Restaurar Archivos Físicos:**
   ```bash
   tar -xzf almacenamiento_YYYYMMDD_HHMMSS.tar.gz -C /var/www/candelaria/almacenamiento/privado/
   ```
4. **Ejecutar Verificación de Integridad:**
   ```bash
   php /var/www/candelaria/pruebas/verificacion_operativa_despliegue.php
   ```

---

## 6. Restricciones Operativas y Activación de Integraciones Externas

> [!CAUTION] Restricción de Pasarelas y WhatsApp
> El sistema se entrega en **MODO SANDBOX CONTROLADO**.
> Queda terminantemente prohibido habilitar cobros con tarjetas reales o despachos masivos de mensajes en vivo hasta completar la siguiente tabla de verificación:

| Proveedor | Condición de Activación en Vivo | Acción Requerida del SysAdmin |
|---|---|---|
| **Culqi** | Cuenta jurídica aprobada por Culqi; llaves `sk_live_...` generadas. | Registrar llaves en `.env`, configurar URL del webhook HTTPS y probar un cobro simbólico autorizado. |
| **Niubiz** | Merchant ID de producción asignado y homologación de seguridad superada. | Cambiar endpoint a producción (`api.vnforapps.com`) y verificar firmas digitales. |
| **Stripe** | Identidad corporativa validada en Stripe Dashboard. | Cargar `sk_live_...` y configurar endpoint de webhook firmado. |
| **Mercado Pago** | Credenciales de producción aprobadas. | Cargar access token de producción y secret de integridad. |
| **Meta WhatsApp** | Número verificado, línea oficial, plantillas aprobadas y saldo prepagado en Meta Business Manager. | Cambiar `WHATSAPP_ADAPTADOR=META_CLOUD_API`, cargar Token de Sistema y verificar handshake del webhook. |
