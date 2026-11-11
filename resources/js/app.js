import './session-guard';
import storkiaLive from './common/live-dom-engine';
import variantManager from './seller/variant-manager';
import imageUploader from './seller/image-uploader';
import productTableRow from './seller/product-table-row';
import sellerStoreSettingsManager from './seller/store-settings-manager';
import searchableSelect from './common/searchable-select';
import accountManager from './buyer/account-manager';
import addressManager from './buyer/address-manager';
import cartPageManager from './buyer/cart-manager';
import categoryPageManager from './buyer/category-manager';
import idCameraCapture from './buyer/id-camera-capture';
import profileManager from './buyer/profile-manager';
import deleteAccountManager from './buyer/delete-account-manager';
import chatWindow from './chat/chat-window';
import { flyToCart, setCartBadge, getCartBadgeCount } from './buyer/cart-fly';

window.flyToCart = flyToCart;
window.setCartBadge = setCartBadge;
window.getCartBadgeCount = getCartBadgeCount;

document.addEventListener('alpine:init', () => {
    Alpine.data('variantManager', variantManager);
    Alpine.data('imageUploader', imageUploader);
    Alpine.data('productTableRow', productTableRow);
    Alpine.data('sellerStoreSettingsManager', sellerStoreSettingsManager);
    Alpine.data('searchableSelect', searchableSelect);
    Alpine.data('accountManager', accountManager);
    Alpine.data('addressManager', addressManager);
    Alpine.data('cartPageManager', cartPageManager);
    Alpine.data('categoryPageManager', categoryPageManager);
    Alpine.data('idCameraCapture', idCameraCapture);
    Alpine.data('profileManager', profileManager);
    Alpine.data('deleteAccountManager', deleteAccountManager);
    Alpine.data('chatWindow', chatWindow);
});

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => storkiaLive.init(), { once: true });
} else {
    storkiaLive.init();
}

import './echo';