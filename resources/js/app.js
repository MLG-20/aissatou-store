

import Alpine from 'alpinejs';
import productFilters from './shop-filters';
import initReveal from './reveal';

window.Alpine = Alpine;

Alpine.data('productFilters', productFilters);

Alpine.start();

initReveal();
