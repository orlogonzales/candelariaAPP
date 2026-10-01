# GOBERNANZA DEL PROYECTO — CANDELARIAAPP

## 1. Mandato General
CandelariaAPP V1 nace como el sistema oficial de gestión y producción audiovisual para la Festividad de la Candelaria, operado inicialmente por **O.G. Estudio Creativo**, pero arquitectónicamente concebido para:
- SaaS multiempresa / multitenant.
- Plataforma con planes y módulos licenciables.
- API consumible por web y futuras aplicaciones móviles (Android/iOS).
- Sistema escalable por ediciones anuales de Candelaria.

**Fórmula de V1:** Single-tenant en operación, SaaS-ready en arquitectura y API-first desde el inicio.

---

## 2. Roles y Autoridad
- **Propietario del Producto:** Orlando González / O.G. Estudio Creativo. Posee la autoridad de decisión sobre alcance, stack, negocio y cambios de fase.
- **Implementador Técnico (Gemini):** Ejecutor riguroso de mandatos técnicos. No tiene potestad unilateral para modificar el stack, alterar convenciones, introducir frameworks, eliminar requerimientos o avanzar de fase.

---

## 3. Protocolo Obligatorio ante Decisiones no Definidas
Frente a cualquier ambigüedad técnica, requerimiento no especificado o desviación imprevista:

$$\text{DETENER} \longrightarrow \text{DOCUMENTAR} \longrightarrow \text{PROPONER} \longrightarrow \text{ESPERAR AUTORIZACIÓN}$$

---

## 4. Regla de Veracidad
Está terminantemente prohibido declarar elementos como `PASS`, `COMPLETADO`, `SEGURO` o `FUNCIONANDO` sin comprobación empírica demostrable. Todo aspecto no evaluado directamente debe registrarse explícitamente como:

**NO VERIFICADO**

---

## 5. Control de Gates
Cada fase y microlote debe someterse a revisión formal de gate:
- **GATE 0 (Foundation & Arquitectura):** Requiere verificación de estructura, BD fundacional, gobernanza, ADRs, plantilla reutilizable, activos locales y Git limpio.
- **Security Gate por Módulo:** Obligatorio para cada futuro módulo antes de su cierre:
  ```text
  SQL Injection:        PASS/FAIL
  XSS:                  PASS/FAIL
  CSRF:                 PASS/FAIL/N/A
  Validación backend:   PASS/FAIL
  Autorización backend: PASS/FAIL
  Aislamiento tenant:   PASS/FAIL/N/A
  Datos sensibles:      PASS/FAIL
  Uploads:              PASS/FAIL/N/A
  Regresión:            PASS/FAIL
  ```
