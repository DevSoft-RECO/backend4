<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Prueba WhatsApp - Alerta V2</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 p-10">

    <div class="max-w-md mx-auto bg-white rounded-xl shadow-lg p-8">
        <h2 class="text-xl font-bold mb-6 text-gray-800 border-b pb-2">Prueba: Alerta Solicitud V2</h2>

        @if(session('status'))
            <div class="bg-green-100 text-green-700 p-3 rounded mb-4 text-sm">{{ session('status') }}</div>
        @endif
        @if(session('error'))
            <div class="bg-red-100 text-red-700 p-3 rounded mb-4 text-sm">{{ session('error') }}</div>
        @endif

        <form action="/enviar-mensaje-manual" method="POST" class="space-y-4">
            @csrf

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase">Teléfono (Sin el 502)</label>
                <div class="flex">
                    <span class="bg-gray-200 p-2 rounded-l text-gray-600">502</span>
                    <input type="number" name="telefono" placeholder="12345678" required class="w-full border p-2 rounded-r focus:outline-none focus:border-blue-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase">Estimado {{1}}</label>
                <input type="text" name="v1_nombre" placeholder="Ej: Juan Pérez" required class="w-full border p-2 rounded">
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase">Tipo Servicio {{2}}</label>
                <input type="text" name="v2_tipo" placeholder="Ej: Soporte Técnico" required class="w-full border p-2 rounded">
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase">Vencimiento {{3}}</label>
                <input type="date" name="v3_fecha" required class="w-full border p-2 rounded">
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase">ID Solicitud {{4}}</label>
                <input type="text" name="v4_id" placeholder="Ej: 9901-A" required class="w-full border p-2 rounded">
            </div>

            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded transition">
                Enviar Plantilla
            </button>
        </form>
    </div>

</body>
</html>
