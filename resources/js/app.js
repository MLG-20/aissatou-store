

import Alpine from 'alpinejs';
import productFilters from './shop-filters';
import cookieConsent from './cookie-consent';
import initReveal from './reveal';
import locationPicker from './location-picker';

window.Alpine = Alpine;

Alpine.data('productFilters', productFilters);
Alpine.data('cookieConsent', cookieConsent);
Alpine.data('locationPicker', locationPicker);

Alpine.start();

initReveal();
