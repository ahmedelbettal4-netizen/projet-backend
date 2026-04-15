// Scanner automatique
document.addEventListener('DOMContentLoaded', function() {
    const codeBarreInput = document.getElementById('code_barre');
    if (codeBarreInput) {
        codeBarreInput.focus();
        
        let lastScan = '';
        codeBarreInput.addEventListener('change', function() {
            if (this.value !== lastScan) {
                lastScan = this.value;
                const form = document.getElementById('scanForm');
                if (form) {
                    form.submit();
                }
            }
        });
    }
    
    // Recherche dans le tableau
    const searchInput = document.getElementById('search');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('.student-table tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    }
});

// Support caméra pour scanner
function initCameraScanner() {
    const cameraBtn = document.getElementById('start-camera');
    if (cameraBtn) {
        cameraBtn.addEventListener('click', function() {
            if (typeof Quagga !== 'undefined') {
                Quagga.init({
                    inputStream: {
                        name: "Live",
                        type: "LiveStream",
                        target: document.querySelector('#preview'),
                        constraints: {
                            facingMode: "environment"
                        },
                    },
                    decoder: {
                        readers: ["code_128_reader", "ean_reader", "ean_8_reader", "code_39_reader"]
                    }
                }, function(err) {
                    if (err) {
                        console.error(err);
                        alert('Erreur d\'accès à la caméra');
                        return;
                    }
                    Quagga.start();
                });
                
                Quagga.onDetected(function(result) {
                    const code = result.codeResult.code;
                    document.getElementById('code_barre').value = code;
                    document.getElementById('scanForm').submit();
                });
            } else {
                alert('La bibliothèque de scan n\'est pas chargée');
            }
        });
    }
}