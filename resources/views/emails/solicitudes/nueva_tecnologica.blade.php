<!DOCTYPE html>
<html>
<head>
    <title>Nueva Solicitud Tecnológica</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">

    <h2 style="color: #0d6efd;">Nueva Solicitud Tecnológica Recibida</h2>

    <p>Se ha registrado una nueva solicitud en el sistema.</p>

    <div style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; border: 1px solid #ddd;">
        <p><strong>Ticket ID:</strong> #{{ $solicitud->id }}</p>
        <p><strong>Título:</strong> {{ $solicitud->titulo }}</p>
        <p><strong>Solicitante:</strong> {{ $solicitud->creadoPor->name ?? 'Desconocido' }}</p>
        <p><strong>Fecha:</strong> {{ $solicitud->created_at->format('d/m/Y H:i') }}</p>
        <p><strong>Agencia:</strong> {{ $solicitud->agencia->nombre ?? 'N/A' }}</p>
    </div>

    <p><strong>Descripción:</strong></p>
    <blockquote style="border-left: 4px solid #eee; padding-left: 10px; color: #555;">
        {{ $solicitud->descripcion }}
    </blockquote>

    <p style="margin-top: 20px;">
        <a href="{{ $urlSolicitud }}" style="background-color: #0d6efd; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Ver Solicitud</a>
    </p>

    <p style="font-size: 0.9em; color: #777; margin-top: 30px;">
        Este es un mensaje automático del Sistema de Gestión YAMANKUTX.
    </p>

</body>
</html>
