/**
 * NIS RESIDENCE CARD ISSUANCE SYSTEM (NIS-RCIS)
 * Officer Electronic Signature & Seal Pad
 */

document.addEventListener('DOMContentLoaded', function() {
    const canvas = document.getElementById('signatureCanvas');
    const clearBtn = document.getElementById('clearSignatureBtn');
    const hiddenSignatureInput = document.getElementById('officerSignatureBase64');

    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    let isDrawing = false;
    let lastX = 0;
    let lastY = 0;

    ctx.strokeStyle = '#1a365d'; // Deep authority blue ink
    ctx.lineWidth = 2.5;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    function getPointerPos(e) {
        const rect = canvas.getBoundingClientRect();
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        return {
            x: (clientX - rect.left) * (canvas.width / rect.width),
            y: (clientY - rect.top) * (canvas.height / rect.height)
        };
    }

    function startDrawing(e) {
        e.preventDefault();
        isDrawing = true;
        const pos = getPointerPos(e);
        lastX = pos.x;
        lastY = pos.y;
    }

    function draw(e) {
        if (!isDrawing) return;
        e.preventDefault();
        const pos = getPointerPos(e);

        ctx.beginPath();
        ctx.moveTo(lastX, lastY);
        ctx.lineTo(pos.x, pos.y);
        ctx.stroke();

        lastX = pos.x;
        lastY = pos.y;

        if (hiddenSignatureInput) {
            hiddenSignatureInput.value = canvas.toDataURL('image/png');
        }
    }

    function stopDrawing() {
        if (isDrawing) {
            isDrawing = false;
            if (hiddenSignatureInput) {
                hiddenSignatureInput.value = canvas.toDataURL('image/png');
            }
        }
    }

    canvas.addEventListener('mousedown', startDrawing);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDrawing);
    canvas.addEventListener('mouseleave', stopDrawing);

    canvas.addEventListener('touchstart', startDrawing, { passive: false });
    canvas.addEventListener('touchmove', draw, { passive: false });
    canvas.addEventListener('touchend', stopDrawing);

    if (clearBtn) {
        clearBtn.addEventListener('click', function(e) {
            e.preventDefault();
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            if (hiddenSignatureInput) {
                hiddenSignatureInput.value = '';
            }
        });
    }
});
