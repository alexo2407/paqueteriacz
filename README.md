# paqueteriacz

Notas importantes:
- La aplicación incluye una constante `DEBUG` en `config/config.php`. Está por
	defecto en `false`. Activa `DEBUG = true` únicamente en entornos de
	desarrollo para permitir logging sanitizado en controladores (por ejemplo,
	`controlador/pedido.php`). Nunca actives DEBUG en producción.