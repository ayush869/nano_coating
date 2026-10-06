import './bootstrap';
import Alpine from 'alpinejs';
import quoteBuilder from './quote-builder.js';
import requestForm from './request-form.js';
import productDesigner from './product-designer.js';
import checkout from './checkout.js';

Alpine.data('quoteBuilder', quoteBuilder);
Alpine.data('requestForm', requestForm);
Alpine.data('productDesigner', productDesigner);
Alpine.data('checkout', checkout);

window.Alpine = Alpine;
Alpine.start();
