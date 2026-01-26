<!DOCTYPE html>
<html lang="es">
<head>
    <title>Solicitud Pendiente de Validación</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px;">

    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h2 style="color: #7c3aed; text-align: center;">✅ Solicitud Resuelta - Requiere Validación</h2>

        <p>Hola <strong>{{ $solicitud->creadoPor->name ?? 'Usuario' }}</strong>,</p>

        <p>Tu solicitud ha sido marcada como resuelta por el equipo técnico. Por favor revisa la solución y valida si el problema ha sido corregido.</p>

        <div style="background-color: #f5f3ff; padding: 15px; border-left: 4px solid #8b5cf6; margin: 20px 0;">
            <p><strong>🆔 Ticket:</strong> #{{ $solicitud->id }}</p>
            <p><strong>📌 Título:</strong> {{ $solicitud->titulo }}</p>
            <p><strong>🛠️ Responsable:</strong> {{ $solicitud->responsable->name ?? 'Sin asignar' }}</p>
        </div>

        <p>Para cerrar el caso o realizar observaciones adicionales, ingresa al sistema:</p>

        <p style="text-align: center; margin-top: 30px;">
            <a href="{{ $urlDetalle }}" style="background-color: #7c3aed; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;">
                Validar y Cerrar Caso
            </a>
        </p>

        <p style="font-size: 12px; color: #aaa; text-align: center; margin-top: 30px;">
            Este es un mensaje automático del Sistema de Gestión YK.
        </p>
    </div>

</body>
</html>
