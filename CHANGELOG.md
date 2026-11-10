# Changelog - josrarefpedido

## 1.1.1 - 2026-11-11
- Clave propia del módulo en el servidor de actualizaciones: ya no comparte clave con otros módulos. Además avisa al servidor cuando se desinstala y del resultado de cada actualización hecha desde la configuración (si falla, con el motivo). Sin cambios en el funcionamiento del módulo.

## 1.1.0
- Avisos de actualización desde el servidor de actualizaciones (una vez al día, al entrar en el back-office).
- En la configuración del módulo: comprobar actualizaciones y "Actualizar ahora" con copia de seguridad previa.
- En PrestaShop 8.1 o superior la versión nueva sale también en Módulos > Gestor de módulos > Actualizaciones.

## 1.0.0
- Versión en producción en sevimotor (PS 9.2). Primera subida al repositorio.
- Cambia la referencia aleatoria de letras del pedido por su ID (también en los pagos del pedido).
- Nota: para que el correo de nuevo pedido de ps_emailalerts salga ya con el ID, ps_emailalerts debe ir después
  de josrarefpedido en el hook actionValidateOrder (Diseño > Posiciones).
