import variantManager from './seller/variant-manager';
import imageUploader from './seller/image-uploader';
import productTableRow from './seller/product-table-row';
import searchableSelect from './common/searchable-select';
import accountManager from './buyer/account-manager';
import addressManager from './buyer/address-manager';
import cartPageManager from './buyer/cart-manager';
import categoryPageManager from './buyer/category-manager';

document.addEventListener('alpine:init', () => {
    Alpine.data('variantManager', variantManager);
    Alpine.data('imageUploader', imageUploader);
    Alpine.data('productTableRow', productTableRow);
    Alpine.data('searchableSelect', searchableSelect);
    Alpine.data('accountManager', accountManager);
    Alpine.data('addressManager', addressManager);
    Alpine.data('cartPageManager', cartPageManager);
    Alpine.data('categoryPageManager', categoryPageManager);
});