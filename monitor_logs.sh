#!/bin/bash
# Monitor de logs en tiempo real
echo "📡 Monitoreando logs de PHP... (Presiona Ctrl+C para detener)"
echo "Intenta cambiar el estado de un lead ahora..."
echo ""
tail -f /Applications/XAMPP/xamppfiles/logs/php_error_log
