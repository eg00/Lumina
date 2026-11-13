import Cropper from 'cropperjs';

declare global {
    interface Window {
        Cropper: typeof Cropper;
    }
}

window.Cropper = Cropper;
