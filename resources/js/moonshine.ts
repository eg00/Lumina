import Cropper from 'cropperjs';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

declare global {
    interface Window {
        Cropper: typeof Cropper;
        L: typeof L;
    }
}

window.Cropper = Cropper;
window.L = L;
