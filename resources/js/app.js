

import Alpine from 'alpinejs';
import productFilters from './shop-filters';
import cookieConsent from './cookie-consent';
import initReveal from './reveal';

window.Alpine = Alpine;

Alpine.data('productFilters', productFilters);
Alpine.data('cookieConsent', cookieConsent);

Alpine.start();

initReveal();
