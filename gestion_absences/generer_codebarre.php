<!DOCTYPE html>
<html>
<head>
    <title>Générer code-barres</title>
    <style>
        body { font-family: Arial; text-align: center; padding: 50px; }
        .barcode { padding: 20px; background: white; display: inline-block; margin: 20px; }
        input { padding: 10px; font-size: 16px; width: 200px; }
        button { padding: 10px 20px; background: #667eea; color: white; border: none; cursor: pointer; }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
</head>
<body>
    <h1>📊 Générateur de code-barres</h1>
    <input type="text" id="code" value="23009155" placeholder="Entrez le code">
    <button onclick="generateBarcode()">Générer code-barres</button>
    <br><br>
    <div class="barcode">
        <svg id="barcode"></svg>
    </div>
    <p>Scannez ce code-barres avec votre caméra</p>
    
    <script>
        function generateBarcode() {
            const code = document.getElementById('code').value;
            JsBarcode("#barcode", code, {
                format: "CODE128",
                lineColor: "#000",
                width: 2,
                height: 100,
                displayValue: true,
                fontSize: 18
            });
        }
        generateBarcode();
    </script>
</body>
</html>