(function () {
    const ENDPOINT = `https://apps.royalhawaiianheritage.com/process.php?domain=${Shopify.shop}`;
    const ACTIONS = {
        GET_OPTIONS: 'get_options',
        GET_PRICE: 'get_price',
        CREATE_PRODUCT: 'create_product',
        DELETE_PRODUCT: 'delete_single_product'
    };

    const productComponent = document.querySelector("product-component");
    if (!productComponent) return;

    const addToCartWrapper = productComponent.querySelector(".product__submit__buttons");
    const addToCartButton = productComponent.querySelector('[name="add"]');
    const productPriceEl = productComponent.querySelector('[data-product-price]');
    const productTitle = document.querySelector('.product__title')?.innerText || "";

    function fetchPost(action, payload) {
        const formData = new URLSearchParams({ action, ...payload });
        return fetch(ENDPOINT, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: formData.toString()
        }).then(res => res.json());
    }

    function updatePrice(productId, productPrice, options) {
        fetchPost(ACTIONS.GET_PRICE, {
            product_id: productId,
            product_price: productPrice,
            product_options: JSON.stringify(options)
        })
            .then(result => {
                const formatted = theme.moneyFormat.replace('{{amount}}', result.product_price);
                productPriceEl.innerText = formatted;
                const fakeBtnPrice = productComponent.querySelector('[name="fakebutton"] .btn__price');
                if (fakeBtnPrice) fakeBtnPrice.innerText = formatted;
                productPriceEl.dataset.productprice = result.product_price;
            })
            .catch(console.error);
    }

    function collectOptions(optionsParent) {
        return [
            ...optionsParent.querySelectorAll('select'),
            ...optionsParent.querySelectorAll('[type="radio"]:checked')
        ].map(el => {
            if (el.tagName === 'SELECT') {
                const selected = el.options[el.selectedIndex];
                return selected?.value
                    ? {
                        product_option_id: selected.dataset.productoptionid,
                        option_value_id: selected.dataset.optionvalueid
                    }
                    : null;
            }
            return el.dataset.productoptionid && el.dataset.optionvalueid
                ? {
                    product_option_id: el.dataset.productoptionid,
                    option_value_id: el.dataset.optionvalueid
                }
                : null;
        }).filter(Boolean);
    }

    function handleOptionChange() {
        const optionsParent = this.closest('[data-customproductoptions]');
        const productId = optionsParent.dataset.productid;
        const productPrice = optionsParent.dataset.productprice;
        const options = collectOptions(optionsParent);
        updatePrice(productId, productPrice, options);
    }

    function bindOptionEvents() {
        const optionsParent = productComponent.querySelector('[data-customproductoptions]');
        if (!optionsParent) return;
        optionsParent.querySelectorAll('select, [type="radio"]').forEach(el => {
            el.addEventListener('change', handleOptionChange);
        });
    }

    function setupFakeButton(productHandle) {
        if (!addToCartButton) return;

        const style = document.createElement("style");
        style.textContent = `.custom-before-cart-btn + [name="add"] {display: none;}`;
        document.head.appendChild(style);

        const newButton = document.createElement("button");
        newButton.type = "button";
        newButton.name = "fakebutton";
        newButton.className = "custom-before-cart-btn btn btn--large btn--solid btn--black";
        newButton.innerHTML = `
            <span class="btn__text">
                ${theme.strings.addToCart} <span class="btn__price">${productPriceEl.innerText}</span>
            </span>
            <span class="btn__added">&nbsp;</span>
            <span class="btn__loader">
                <svg height="18" width="18" class="svg-loader">
                    <circle r="7" cx="9" cy="9"></circle>
                    <circle stroke-dasharray="87.96459430051421 87.96459430051421" r="7" cx="9" cy="9"></circle>
                </svg>
            </span>`;

        addToCartButton.parentNode.insertBefore(newButton, addToCartButton);

        newButton.addEventListener('click', () => {
            newButton.classList.add('is-loading');
            const newProductPrice = productPriceEl.dataset.productprice || "";
            const newProductImage = productComponent.querySelector('product-images img')?.src || "";
            const newProductTags = `handle__${productHandle},custom_product`;

            fetchPost(ACTIONS.CREATE_PRODUCT, {
                total_price: newProductPrice,
                title: productTitle,
                img: newProductImage,
                tags: newProductTags
            })
                .then(result => {
                    const customOptions = productComponent.querySelector('[data-customproductoptions]');
                    if (customOptions) {
                        customOptions.insertAdjacentHTML("beforebegin", `<input type="hidden" name="id" value="${result.variant_id}">`);
                        // customOptions.insertAdjacentHTML("beforebegin", `<input type="hidden" name="properties[product_id]" value="${result.product_id}">`);
                        addToCartButton.dispatchEvent(new Event("click", { bubbles: true }));
                        setTimeout(() => {
                            newButton.classList.remove('is-loading');
                        }, 500);
                    }
                })
                .catch(console.error);
        });
    }

    if (window.location.pathname.includes("/products/")) {
        const urlParts = window.location.pathname.split("/");
        const productHandle = urlParts[urlParts.indexOf("products") + 1] || "";

        if (productHandle) {
            fetchPost(ACTIONS.GET_OPTIONS, { handle: productHandle, title: productTitle })
                .then(result => {
                    if (addToCartWrapper) {
                        addToCartWrapper.insertAdjacentHTML("beforebegin", result.form);
                        bindOptionEvents();

                        const optionsParent = productComponent.querySelector('[data-customproductoptions]');
                        if (optionsParent) {
                            const productId = optionsParent.dataset.productid;
                            const productPrice = optionsParent.dataset.productprice;
                            const options = collectOptions(optionsParent);
                            updatePrice(productId, productPrice, options);
                        }
                    }
                })
                .catch(console.error);

            setupFakeButton(productHandle);
        }
    }
    document.addEventListener("click", function (e) {
        const target = e.target.closest("[data-item-remove]");
        if (target) {
            fetchPost(ACTIONS.DELETE_PRODUCT, { variantId: target.getAttribute('data-id').split(':')[0] })
                .then(result => {
                    console.log(result);
                })
                .catch(console.error);
        }
    });

})();
