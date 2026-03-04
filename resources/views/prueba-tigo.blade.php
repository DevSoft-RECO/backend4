<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prueba Envío SMS Tigo</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 p-10">

    <div class="max-w-md mx-auto bg-white rounded-xl shadow-lg p-8">
        <div class="flex items-center justify-between mb-6 border-b pb-2">
            <h2 class="text-xl font-bold text-gray-800">Prueba: SMS Tigo Business</h2>
            <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-2.5 py-0.5 rounded">TIGO B2B</span>
        </div>

        @if(session('status'))
            <div class="bg-green-100 text-green-700 p-3 rounded mb-4 text-sm">{{ session('status') }}</div>
        @endif
        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 p-3 rounded mb-4 text-sm whitespace-pre-wrap break-words">{{ session('error') }}</div>
        @endif

        <form action="/enviar-mensaje-tigo" method="POST" class="space-y-4">
            @csrf

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Teléfono (Sin el 502)</label>
                <div class="flex">
                    <span class="bg-gray-200 p-2 rounded-l text-gray-600 border border-r-0 border-gray-300">502</span>
                    <input type="number" name="telefono" placeholder="12345678" required class="w-full border border-gray-300 p-2 rounded-r focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Cuerpo del Mensaje</label>
                <textarea name="mensaje" rows="4" placeholder="Escribe tu mensaje aquí..." required class="w-full border border-gray-300 p-2 rounded focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"></textarea>
                <p class="text-[10px] text-gray-400 mt-1">Recuerda que los SMS tienen un límite de caracteres, generalmente 160.</p>
            </div>

            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded transition shadow-md mt-4">
                Enviar SMS
            </button>
        </form>
    </div>

</body>
</html>
