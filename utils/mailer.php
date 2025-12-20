<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/email_config.php';

/**
 * Mailer - Utilidad para envío de emails usando PHPMailer
 */
class Mailer {
    
    /**
     * Configurar instancia de PHPMailer con credenciales SMTP
     * 
     * @return PHPMailer
     */
    private static function configurar() {
        $mail = new PHPMailer(true);
        
        try {
            // Configuración del servidor SMTP
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USERNAME;
            $mail->Password   = SMTP_PASSWORD;
            $mail->SMTPSecure = SMTP_ENCRYPTION;
            $mail->Port       = SMTP_PORT;
            $mail->CharSet    = SMTP_CHARSET;
            
            // Remitente
            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            
            // Debug (solo en desarrollo)
            $mail->SMTPDebug = SMTP_DEBUG;
            
        } catch (Exception $e) {
            error_log('Error configurando PHPMailer: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
        }
        
        return $mail;
    }
    
    /**
     * Enviar email genérico
     * 
     * @param string $destinatario Email del destinatario
     * @param string $asunto Asunto del email
     * @param string $cuerpoHTML Cuerpo del email en HTML
     * @param string $cuerpoTexto Cuerpo alternativo en texto plano (opcional)
     * @return array ['success' => bool, 'message' => string]
     */
    public static function enviarEmail($destinatario, $asunto, $cuerpoHTML, $cuerpoTexto = '') {
        try {
            $mail = self::configurar();
            
            // Destinatario
            $mail->addAddress($destinatario);
            
            // Contenido
            $mail->isHTML(true);
            $mail->Subject = $asunto;
            $mail->Body    = $cuerpoHTML;
            $mail->AltBody = (!empty($cuerpoTexto)) ? $cuerpoTexto : strip_tags($cuerpoHTML);
            
            $mail->send();
            
            return [
                'success' => true,
                'message' => 'Email enviado correctamente'
            ];
            
        } catch (Exception $e) {
            error_log('Error enviando email: ' .  $mail->ErrorInfo, 3, __DIR__ . '/../logs/errors.log');
            return [
                'success' => false,
                'message' => 'No se pudo enviar el email: ' . $mail->ErrorInfo
            ];
        }
    }
    
    /**
     * Enviar email de recuperación de contraseña
     * 
     * @param string $email Email del destinatario
     * @param string $token Token de recuperación
     * @return array ['success' => bool, 'message' => string]
     */
    public static function enviarEmailRecuperacion($email, $token) {
        $resetLink = RUTA_URL . 'reset-password?token=' . urlencode($token);
        
        $asunto = 'Recuperación de Contraseña - Paqueteria CruzValle';
        
        $cuerpoHTML = self::templateRecuperacion($resetLink);
        
        $cuerpoTexto = "Hola,\n\n"
                     . "Hemos recibido una solicitud para restablecer tu contraseña.\n\n"
                     . "Para continuar, haz clic en el siguiente enlace:\n"
                     . $resetLink . "\n\n"
                     . "Este enlace expirará en 1 hora por seguridad.\n\n"
                     . "Si no solicitaste este cambio, puedes ignorar este mensaje.\n\n"
                     . "Saludos,\n"
                     . "Equipo de Paqueteria CruzValle";
        
        return self::enviarEmail($email, $asunto, $cuerpoHTML, $cuerpoTexto);
    }
    
    /**
     * Template HTML para email de recuperación
     * 
     * @param string $resetLink Link de recuperación
     * @return string HTML del email
     */
    private static function templateRecuperacion($resetLink) {
        return '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperación de Contraseña</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <table role="presentation" style="width: 100%; border-collapse: collapse;">
        <tr>
            <td align="center" style="padding: 40px 0;">
                <table role="presentation" style="width: 600px; border-collapse: collapse; background-color: #ffffff; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="padding: 40px 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px;">🔒 Recuperación de Contraseña</h1>
                        </td>
                    </tr>
                    
                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <p style="margin: 0 0 20px; font-size: 16px; line-height: 1.6; color: #333333;">
                                Hola,
                            </p>
                            <p style="margin: 0 0 20px; font-size: 16px; line-height: 1.6; color: #333333;">
                                Hemos recibido una solicitud para restablecer la contraseña de tu cuenta en <strong>Paqueteria CruzValle</strong>.
                            </p>
                            <p style="margin: 0 0 30px; font-size: 16px; line-height: 1.6; color: #333333;">
                                Para crear una nueva contraseña, haz clic en el siguiente botón:
                            </p>
                            
                            <!-- CTA Button -->
                            <table role="presentation" style="margin: 0 auto;">
                                <tr>
                                    <td style="border-radius: 6px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                        <a href="' . $resetLink . '" 
                                           style="display: inline-block; padding: 16px 36px; font-size: 16px; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold;">
                                            Restablecer Contraseña
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Link alternativo -->
                            <p style="margin: 30px 0 20px; font-size: 14px; line-height: 1.6; color: #666666; text-align: center;">
                                Si el botón no funciona, copia y pega este enlace en tu navegador:
                            </p>
                            <p style="margin: 0 0 30px; font-size: 13px; color: #667eea; word-break: break-all; text-align: center;">
                                ' . $resetLink . '
                            </p>
                            
                            <!-- Warning -->
                            <div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0;">
                                <p style="margin: 0; font-size: 14px; color: #856404;">
                                    ⚠️ <strong>Importante:</strong> Este enlace expirará en <strong>1 hora</strong> por motivos de seguridad.
                                </p>
                            </div>
                            
                            <p style="margin: 20px 0 0; font-size: 14px; line-height: 1.6; color: #666666;">
                                Si no solicitaste este cambio de contraseña, puedes ignorar este correo de forma segura. Tu contraseña actual no se modificará.
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 30px; background-color: #f8f9fa; text-align: center; border-top: 1px solid #dee2e6;">
                            <p style="margin: 0 0 10px; font-size: 14px; color: #6c757d;">
                                Saludos,<br>
                                <strong>Equipo de Paqueteria CruzValle</strong>
                            </p>
                            <p style="margin: 0; font-size: 12px; color: #adb5bd;">
                                Este es un email automático, por favor no respondas a este mensaje.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
        ';
    }
}
