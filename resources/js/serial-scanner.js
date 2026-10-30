import { BrowserMultiFormatReader } from '@zxing/browser';
import { BarcodeFormat, DecodeHintType } from '@zxing/library';

// Formats used on serial number stickers (Code 128/39 mostly) plus QR/DataMatrix.
const hints = new Map([
    [DecodeHintType.POSSIBLE_FORMATS, [
        BarcodeFormat.CODE_128,
        BarcodeFormat.CODE_39,
        BarcodeFormat.CODE_93,
        BarcodeFormat.EAN_13,
        BarcodeFormat.UPC_A,
        BarcodeFormat.ITF,
        BarcodeFormat.QR_CODE,
        BarcodeFormat.DATA_MATRIX,
    ]],
    [DecodeHintType.TRY_HARDER, true],
]);

function buildOverlay() {
    const overlay = document.createElement('div');
    overlay.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.85);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:16px;padding:16px;';

    const video = document.createElement('video');
    video.setAttribute('playsinline', '');
    video.muted = true;
    video.style.cssText = 'width:100%;max-width:640px;max-height:70vh;border-radius:12px;background:#000;object-fit:cover;';

    const hint = document.createElement('p');
    hint.textContent = 'Apuntá la cámara al código de barras del número de serie';
    hint.style.cssText = 'color:#fff;font:14px system-ui,sans-serif;text-align:center;margin:0;';

    const cancel = document.createElement('button');
    cancel.type = 'button';
    cancel.textContent = 'Cancelar';
    cancel.style.cssText = 'padding:10px 20px;border-radius:8px;border:0;background:#fff;color:#111;font:600 14px system-ui,sans-serif;cursor:pointer;';

    overlay.append(video, hint, cancel);
    document.body.append(overlay);

    return { overlay, video, hint, cancel };
}

/**
 * Opens the camera and resolves with the first barcode read.
 * Rejects when cancelled or when the camera is not available
 * (it requires HTTPS outside localhost).
 */
window.pezScanSerial = function () {
    return new Promise((resolve, reject) => {
        const { overlay, video, hint, cancel } = buildOverlay();
        const reader = new BrowserMultiFormatReader(hints);
        let controls = null;

        const close = () => {
            controls?.stop();
            overlay.remove();
        };

        cancel.addEventListener('click', () => {
            close();
            reject(new Error('cancelled'));
        });

        reader
            .decodeFromConstraints({ video: { facingMode: 'environment' } }, video, (result, error, ctrl) => {
                controls = ctrl;

                if (result) {
                    close();
                    resolve(result.getText().trim());
                }
            })
            .then((ctrl) => {
                controls = ctrl;
            })
            .catch(() => {
                hint.textContent = 'No se pudo abrir la cámara. Revisá los permisos del navegador.';
            });
    });
};
