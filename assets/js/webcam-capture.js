/**
 * NIS RESIDENCE CARD ISSUANCE SYSTEM (NIS-RCIS)
 * Biometric Photo Capture (Live Webcam + File Upload with Preview)
 */

document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('photoFileInput');
    const previewImg = document.getElementById('photoPreviewImg');
    const placeholder = document.getElementById('photoPlaceholder');
    const hiddenBase64 = document.getElementById('photoBase64');
    const startCamBtn = document.getElementById('startCameraBtn');
    const snapBtn = document.getElementById('snapPhotoBtn');
    const stopCamBtn = document.getElementById('stopCameraBtn');
    const webcamContainer = document.getElementById('webcamContainer');
    const video = document.getElementById('webcamVideo');
    const cameraStatusMsg = document.getElementById('cameraStatusMsg');
    const cameraSelectContainer = document.getElementById('cameraSelectContainer');
    const cameraSelect = document.getElementById('cameraSelect');
    const cameraTipMsg = document.getElementById('cameraTipMsg');

    let activeStream = null;
    let selectedDeviceId = null;
    let darknessCheckTimeout = null;

    // 1. Regular File Upload Handling with Immediate Preview
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;

            if (!file.type.startsWith('image/')) {
                alert('Please select a valid image file (JPG, PNG).');
                return;
            }

            const reader = new FileReader();
            reader.onload = function(event) {
                if (previewImg) {
                    previewImg.src = event.target.result;
                    previewImg.style.display = 'block';
                }
                if (placeholder) {
                    placeholder.style.display = 'none';
                }
                if (hiddenBase64) {
                    hiddenBase64.value = event.target.result;
                }
            };
            reader.readAsDataURL(file);
        });
    }

    // 2. Discover available video input devices
    async function populateVideoDevices() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) return;
        try {
            const devices = await navigator.mediaDevices.enumerateDevices();
            const videoDevices = devices.filter(d => d.kind === 'videoinput');
            
            if (cameraSelect && videoDevices.length > 0) {
                cameraSelect.innerHTML = '';
                videoDevices.forEach((dev, idx) => {
                    const opt = document.createElement('option');
                    opt.value = dev.deviceId;
                    opt.text = dev.label || `Camera ${idx + 1}`;
                    if (selectedDeviceId && dev.deviceId === selectedDeviceId) {
                        opt.selected = true;
                    }
                    cameraSelect.appendChild(opt);
                });

                if (videoDevices.length > 1) {
                    if (cameraSelectContainer) cameraSelectContainer.style.display = 'block';
                } else {
                    if (cameraSelectContainer) cameraSelectContainer.style.display = 'none';
                }
            }
        } catch (e) {
            console.warn('Could not enumerate media devices:', e);
        }
    }

    // 3. Start Camera Stream Function
    async function startCamera(deviceId = null) {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            alert('Live camera access is not supported by your browser or is restricted. Please access via localhost or use the file upload option.');
            return;
        }

        // Show container immediately so browser does not suppress video engine
        if (webcamContainer) webcamContainer.style.display = 'block';
        if (cameraStatusMsg) {
            cameraStatusMsg.style.display = 'block';
            cameraStatusMsg.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Initializing camera feed...';
        }
        if (cameraTipMsg) cameraTipMsg.style.display = 'none';
        if (startCamBtn) startCamBtn.style.display = 'none';
        if (snapBtn) snapBtn.style.display = 'inline-flex';
        if (stopCamBtn) stopCamBtn.style.display = 'inline-flex';

        // Stop any currently running stream before re-acquiring
        if (activeStream) {
            activeStream.getTracks().forEach(t => t.stop());
            activeStream = null;
        }

        // Build constraints
        const videoConstraints = {
            width: { ideal: 640 },
            height: { ideal: 480 }
        };
        if (deviceId) {
            videoConstraints.deviceId = { exact: deviceId };
        }

        try {
            let stream = null;
            try {
                stream = await navigator.mediaDevices.getUserMedia({
                    video: videoConstraints,
                    audio: false
                });
            } catch (constraintErr) {
                console.warn('Constrained getUserMedia failed, falling back to basic {video: true}:', constraintErr);
                stream = await navigator.mediaDevices.getUserMedia({
                    video: true,
                    audio: false
                });
            }

            activeStream = stream;

            // Set video attributes for strict autoplay policies
            video.muted = true;
            video.setAttribute('muted', '');
            video.setAttribute('playsinline', '');
            video.setAttribute('autoplay', '');
            video.srcObject = stream;

            // Call play() immediately
            try {
                await video.play();
            } catch (playErr) {
                console.warn('Video play promise deferred:', playErr);
            }

            // Hide status message once video starts playing
            video.onplaying = function() {
                if (cameraStatusMsg) cameraStatusMsg.style.display = 'none';
                checkFrameBrightness();
            };

            // In case readyState is already loaded
            if (video.readyState >= 2) {
                if (cameraStatusMsg) cameraStatusMsg.style.display = 'none';
                checkFrameBrightness();
            }

            // Update device list once permissions are granted
            await populateVideoDevices();

        } catch (err) {
            console.error('Camera access error:', err);
            if (cameraStatusMsg) cameraStatusMsg.style.display = 'none';
            stopCamera();

            let msg = 'Unable to access camera: ';
            if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
                msg += 'Camera permission was denied in your browser settings. Please allow camera permissions.';
            } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
                msg += 'No webcam detected on this computer.';
            } else if (err.name === 'NotReadableError' || err.name === 'TrackStartError') {
                msg += 'Camera is in use by another application (e.g. Teams, Zoom, or another browser window).';
            } else {
                msg += (err.message || 'Please use file upload instead.');
            }
            alert(msg);
        }
    }

    // 4. Sample video canvas to diagnose if camera output is pitch black (shutter closed / IR lens)
    function checkFrameBrightness() {
        if (darknessCheckTimeout) clearTimeout(darknessCheckTimeout);
        darknessCheckTimeout = setTimeout(() => {
            if (!video || !activeStream) return;
            try {
                const testCanvas = document.createElement('canvas');
                testCanvas.width = 16;
                testCanvas.height = 16;
                const testCtx = testCanvas.getContext('2d');
                testCtx.drawImage(video, 0, 0, 16, 16);
                const frameData = testCtx.getImageData(0, 0, 16, 16).data;
                let totalBrightness = 0;
                for (let i = 0; i < frameData.length; i += 4) {
                    totalBrightness += frameData[i] + frameData[i+1] + frameData[i+2];
                }
                const avgBrightness = totalBrightness / (16 * 16 * 3);
                // If practically pitch black (average pixel value < 4 out of 255)
                if (avgBrightness < 4) {
                    if (cameraTipMsg) cameraTipMsg.style.display = 'block';
                } else {
                    if (cameraTipMsg) cameraTipMsg.style.display = 'none';
                }
            } catch (e) {
                // Ignore canvas security errors if any
            }
        }, 1200);
    }

    // 5. Wire button events
    if (startCamBtn) {
        startCamBtn.addEventListener('click', function() {
            startCamera(selectedDeviceId);
        });
    }

    if (cameraSelect) {
        cameraSelect.addEventListener('change', function() {
            selectedDeviceId = this.value;
            startCamera(selectedDeviceId);
        });
    }

    if (snapBtn && video) {
        snapBtn.addEventListener('click', function() {
            if (!video || !activeStream) return;

            const canvas = document.createElement('canvas');
            canvas.width = 400;
            canvas.height = 500;
            const ctx = canvas.getContext('2d');

            const vWidth = video.videoWidth || 640;
            const vHeight = video.videoHeight || 480;
            const targetRatio = 4 / 5;

            let sWidth = vWidth;
            let sHeight = vWidth / targetRatio;
            if (sHeight > vHeight) {
                sHeight = vHeight;
                sWidth = vHeight * targetRatio;
            }
            const sx = Math.max(0, (vWidth - sWidth) / 2);
            const sy = Math.max(0, (vHeight - sHeight) / 2);

            ctx.drawImage(video, sx, sy, sWidth, sHeight, 0, 0, canvas.width, canvas.height);

            const photoDataUrl = canvas.toDataURL('image/jpeg', 0.92);

            if (previewImg) {
                previewImg.src = photoDataUrl;
                previewImg.style.display = 'block';
            }
            if (placeholder) {
                placeholder.style.display = 'none';
            }
            if (hiddenBase64) {
                hiddenBase64.value = photoDataUrl;
            }

            stopCamera();
        });
    }

    if (stopCamBtn) {
        stopCamBtn.addEventListener('click', function() {
            stopCamera();
        });
    }

    function stopCamera() {
        if (darknessCheckTimeout) {
            clearTimeout(darknessCheckTimeout);
            darknessCheckTimeout = null;
        }
        if (activeStream) {
            activeStream.getTracks().forEach(track => track.stop());
            activeStream = null;
        }
        if (video) {
            video.srcObject = null;
        }
        if (webcamContainer) webcamContainer.style.display = 'none';
        if (cameraTipMsg) cameraTipMsg.style.display = 'none';
        if (startCamBtn) startCamBtn.style.display = 'inline-flex';
        if (snapBtn) snapBtn.style.display = 'none';
        if (stopCamBtn) stopCamBtn.style.display = 'none';
    }
});
