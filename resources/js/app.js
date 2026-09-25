import variantManager from './seller/variant-manager';
import imageUploader from './seller/image-uploader';
import productTableRow from './seller/product-table-row';
import searchableSelect from './common/searchable-select';

document.addEventListener('alpine:init', () => {
    Alpine.data('variantManager', variantManager);
    Alpine.data('imageUploader', imageUploader);
    Alpine.data('productTableRow', productTableRow);
    Alpine.data('searchableSelect', searchableSelect);
});