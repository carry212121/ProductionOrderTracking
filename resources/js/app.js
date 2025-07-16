import './bootstrap';

import Alpine from 'alpinejs';
import './events/searchEvent';
import './events/product-searchEvent';
import { setupFilterEvents } from './events/filterEvents';

setupFilterEvents(
    'filterToggleBtn',
    'filterPanel',
    '.filter-option',
    '.product-card'
);

window.Alpine = Alpine;

Alpine.start();
