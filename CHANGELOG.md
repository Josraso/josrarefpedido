# Changelog - josrarefpedido

## 1.0.0
- Versión en producción en sevimotor (PS 9.2). Primera subida al repositorio.
- Cambia la referencia aleatoria de letras del pedido por su ID (también en los pagos del pedido).
- Nota: para que el correo de nuevo pedido de ps_emailalerts salga ya con el ID, ps_emailalerts debe ir después
  de josrarefpedido en el hook actionValidateOrder (Diseño > Posiciones).
