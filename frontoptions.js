(function () {
    const ENDPOINT = `https://apps.royalhawaiianheritage.com/process.php?domain=${Shopify.shop}`;
    const ACTIONS = {
        GET_OPTIONS: 'get_options',
        GET_PRICE: 'get_price'
    };

    const productComponent = document.querySelector("product-component");
    if (!productComponent) return;

    const addToCartWrapper = productComponent.querySelector(".product__submit__buttons");
    const addToCartButton = productComponent.querySelector('[name="add"]');
    const addToCartQuantity = addToCartWrapper.querySelector('quantity-counter');
    const productPriceEl = productComponent.querySelector('[data-product-price]');
    const productTitle = document.querySelector('.product__title')?.innerText || "";
    const body = document.body;
    function fetchPost(action, payload) {
        const formData = new URLSearchParams({ action, ...payload });
        return fetch(ENDPOINT, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: formData.toString()
        }).then(res => res.json());
    }

    function updatePrice(productId, productPrice, options) {
        addToCartButton.disabled = true;
        addToCartButton.classList.add("is-loading");

        fetchPost(ACTIONS.GET_PRICE, {
            product_id: productId,
            product_price: productPrice,
            product_options: JSON.stringify(options)
        })
            .then(result => {
                const formatted = theme.moneyFormat.replace('{{amount}}', result.product_price);

                // Update main product price
                productPriceEl.innerText = formatted;
                productPriceEl.dataset.productprice = result.product_price;

                // Update original Add to Cart button
                const btnPrice = productComponent.querySelector('.btn__price');
                if (btnPrice) btnPrice.innerText = formatted;

                // Update drawer Add to Cart button price
                const drawerBtnPrice = productComponent.querySelector('#drawerAddToCart .btn__price');
                const drawerPrice = productComponent.querySelector('#drawerPrice');
                if (drawerBtnPrice) drawerBtnPrice.innerText = formatted;
                if (drawerPrice) drawerPrice.innerText = formatted;

                // Hidden price field for line item property
                let hiddenPrice = productComponent.querySelector('#productPrice');
                if (!hiddenPrice) {
                    hiddenPrice = document.createElement('input');
                    hiddenPrice.type = 'hidden';
                    hiddenPrice.id = 'productPrice';
                    hiddenPrice.name = 'properties[_Product Price]';
                    hiddenPrice.required = true;
                    addToCartWrapper.insertAdjacentElement("beforeend", hiddenPrice);
                }
                hiddenPrice.value = result.raw_price;

                addToCartButton.disabled = false;
                addToCartButton.classList.remove("is-loading");
            })
            .catch(err => {
                console.error(err);
                addToCartButton.classList.remove("is-loading");
            });
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

    function handleOptionChange(e) {
        const optionsParent = e.target.closest('[data-customproductoptions]');
        if (!optionsParent) return;
        const productId = optionsParent.dataset.productid;
        // const productPrice = optionsParent.dataset.productprice;
        const productPrice = productPriceEl.dataset.price;
        const options = collectOptions(optionsParent);
        updatePrice(productId, productPrice, options);
    }

    function bindOptionEvents(scope) {
        const optionsParent = scope.querySelector('[data-customproductoptions]');
        if (!optionsParent) return;
        optionsParent.querySelectorAll('select, [type="radio"]').forEach(el => {
            el.addEventListener('change', handleOptionChange);
        });
    }

    const INLINE_OPTIONS_CSS = `
    .custom-product-options-inline-wrap .custom-product-options [type=radio]{display:none}
    .custom-product-options-inline-wrap .custom-product-options [type=radio]+label{display:flex;align-items:center;justify-content:center;flex-direction:column;flex:0 0 calc(100% / 4 - 15px / 4);gap:10px;padding:10px;border:1px solid #ccc;font-size:14px;color:#000;transition:.3s;cursor:pointer;text-align:center;border-radius:5px}
    .custom-product-options-inline-wrap .custom-product-options>div{width:100%;display:flex;flex-wrap:wrap;gap:10px 5px}
    .custom-product-options-inline-wrap .custom-product-options>div legend{flex:0 0 100%;color:#000;font-size:16px;padding:0}
    .custom-product-options-inline-wrap .custom-product-options [type=radio]:checked+label{border-color:#000}
    .custom-product-options-inline-wrap .custom-product-options input[type=text],
    .custom-product-options-inline-wrap .custom-product-options select{color:#000;font-size:14px;padding:10px;border:1px solid #000;display:block;width:100%;margin:0}
    .custom-product-options-inline-wrap .custom-product-options [type=radio]+label{text-transform:capitalize}
    .custom-product-options-inline-wrap .custom-product-options [type=radio]+label img{width:30px;height:30px;object-fit:cover;border-radius:50%;display:block}
    .custom-product-options-inline-wrap .custom-select-wrap{position:relative;display:inline-block;width:100%}
    .custom-product-options-inline-wrap .custom-select-hidden{position:absolute;left:0;top:0;width:100%;height:100%;opacity:0;pointer-events:none;z-index:-1}
    .custom-product-options-inline-wrap .custom-select{position:relative}
    .custom-product-options-inline-wrap .custom-select .selected{border:1px solid #ccc;padding:8px;cursor:pointer;display:flex;align-items:center;gap:8px;background:#fff;user-select:none}
    .custom-product-options-inline-wrap .custom-select .options{position:absolute;width:100%;border:1px solid #ccc;border-top:none;background:#fff;display:none;z-index:20;max-height:180px;overflow-y:auto}
    .custom-product-options-inline-wrap .custom-select .option{padding:8px;cursor:pointer;display:flex;align-items:center;gap:8px}
    .custom-product-options-inline-wrap .custom-select .option:hover{background:#f0f0f0}
    .custom-product-options-inline-wrap .custom-select .option.active{background:#f5f5f5;font-weight:600}
    .custom-product-options-inline-wrap .custom-select img{width:24px;height:24px;object-fit:cover}
    .custom-product-options-inline-wrap{margin-bottom:20px}
    `;

    function createDrawer(addToCartWrapper, productComponent) {
        if (document.getElementById("customDrawer")) return;

        const drawerHTML = `
        <style>
        .custom-drawer,.custom-overlay{position:fixed;top:0;height:100%}.custom-overlay{left:0;width:100%;background:rgba(0,0,0,.5);z-index:999;display:none}.custom-overlay.show,.drawer-close{display:block}.custom-drawer{right:0;width:520px;background:#fff;box-shadow:-2px 0 5px rgba(0,0,0,.3);z-index:999999999;padding:20px;transition:.3s;transform:translateX(100%)}.custom-drawer.open{transform:translateX(0)}.drawer-close{background:0 0;border:none;cursor:pointer;outline:0;padding:0;width:20px;height:20px}#drawerAddToCart{margin-top:15px;width:100%}body.customizeropen main#MainContent{position:relative;z-index:9999999}#drawerContent{overflow-y:auto;height:calc(100% - 420px);width:100%;scrollbar-width:none;scrollbar-width:thin;scrollbar-color:#000 #f5f5f5;padding-right:10px}.custom-product-options [type=radio]{display:none}.custom-product-options [type=radio]+label{display:flex;align-items:center;justify-content: center;flex-direction:column;flex:0 0 calc(100% / 4 - 15px / 4);gap:10px;padding:10px;border:1px solid #ccc;font-size:14px;color:#000;transition:.3s;cursor:pointer;text-align:center;border-radius:5px}.custom-product-options>div{width:100%;display:flex;flex-wrap:wrap;gap:10px 5px}.custom-product-options>div legend{flex:0 0 100%;color:#000;font-size:16px;padding:0}.custom-drawer-footer .quantity-selector *,.custom-product-options [type=radio]:checked+label{border-color:#000}.custom-product-options input[type=text],.custom-product-options select{color:#000;font-size:14px;padding:10px;border:1px solid #000;display:block;width:100%;margin:0}span#drawerPrice{font-size:18px;color:#000;letter-spacing:.5px;line-height:1}.custom-drawer-header{display:flex;align-items:center;justify-content:space-between;padding-bottom:15px;margin-bottom:15px;border-bottom:1px solid #000}.drawer-close svg{transform:rotate(45deg);width:20px;height:20px;display:block}.custom-drawer-footer quantity-counter{width:100px}.custom-drawer-footer{padding-top:15px;margin-top:15px;border-top:1px solid #000}body.customizeropen .product__wrapper{position:relative;z-index:999999}#drawerContent::-webkit-scrollbar{width:8px}#drawerContent::-webkit-scrollbar-track{background:#f5f5f5;border-radius:4px}#drawerContent::-webkit-scrollbar-thumb{background-color:#000;border-radius:4px;border:1px solid #f5f5f5}#drawerContent::-webkit-scrollbar-thumb:hover{background-color:#444}.engraving-instructions{margin-bottom:15px;background-color:#f9f8f7;width:100%;border-radius:5px;padding:15px;font-size:14px;color:#000}.engraving-instructions>p{margin-top:0}ul.engraving-instruction-list{margin:0;list-style:none;padding:0;color:#000;font-size:14px}.engraving-instruction-list li span{display:block;width:5px;height:5px;background-color:#000;border-radius:50%;min-width:5px}.engraving-instruction-list li{display:flex;align-items:center;gap:10px;line-height:1.2}@media only screen and (max-width:767px){.custom-drawer{max-width:100%;width:400px}}.no-scroll{overflow:hidden}.custom-product-options [type=radio]+label img{width:30px;height:30px;object-fit:cover;border-radius:50%;display:block}
        .custom-select-wrap{position:relative;display:inline-block;width:100%}.custom-select-hidden{position:absolute;left:0;top:0;width:100%;height:100%;opacity:0;pointer-events:none;z-index:-1}.custom-select{position:relative}.custom-select .selected{border:1px solid #ccc;padding:8px;cursor:pointer;display:flex;align-items:center;gap:8px;background:#fff;user-select:none}.custom-select .options{position:absolute;width:100%;border:1px solid #ccc;border-top:none;background:#fff;display:none;z-index:20;max-height:180px;overflow-y:auto}.custom-select .option{padding:8px;cursor:pointer;display:flex;align-items:center;gap:8px}.custom-select .option:hover{background:#f0f0f0}.custom-select .option.active{background:#f5f5f5;font-weight:600}.custom-select img{width:24px;height:24px;object-fit:cover}
       .custom-product-options [type=radio]+label{text-transform: capitalize;}
        </style>
        <div class="custom-overlay" id="customOverlay"></div>
        <div class="custom-drawer" id="customDrawer">
        <div class="custom-drawer-header">
            <span id="drawerPrice"></span>
            <button type="button" class="drawer-close" id="drawerClose">
            <svg class="icon-plus_minus" viewBox="0 0 9 9" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd" clip-rule="evenodd"
                d="M4.51147 0.00134277C4.81147 0.00134277 5.01147 0.201343 5.01147 0.501343L5.01147 8.50134C5.01147 8.63395 4.9588 8.76113 4.86503 8.8549C4.77126 8.94866 4.64408 9.00134 4.51147 9.00134C4.37887 9.00134 4.25169 8.94866 4.15792 8.8549C4.06415 8.76113 4.01147 8.63395 4.01147 8.50134L4.01147 0.501343C4.01147 0.368735 4.06415 0.241558 4.15792 0.147789C4.25169 0.0540212 4.37887 0.00134277 4.51147 0.00134277Z"
                fill="currentColor"></path>
                <path fill-rule="evenodd" clip-rule="evenodd"
                d="M0 4.5C0 4.2 0.2 4 0.5 4H8.5C8.63261 4 8.75979 4.05268 8.85355 4.14645C8.94732 4.24021 9 4.36739 9 4.5C9 4.63261 8.94732 4.75979 8.85355 4.85355C8.75979 4.94732 8.63261 5 8.5 5H0.5C0.367392 5 0.240215 4.94732 0.146447 4.85355C0.0526784 4.75979 0 4.63261 0 4.5Z"
                fill="currentColor"></path>
            </svg></button>
        </div>
        <div id="drawerContent"></div>
        <div class="custom-drawer-footer">
        <div class="engraving-instructions">
        <p class="engraving-instruction-text">I understand that all custom items are made especially for me, are final sale, and that:</p>
        <ul class="engraving-instruction-list">
            <li><span></span>Orders are not eligible for exchanges, refunds, or cancellations.</li>
            <li><span></span>No changes can be made once the order has been submitted.</li>
            <li><span></span>Due to the handmade process, engraving may vary slightly from samples and previews.</li>
            <li><span></span>Lettering may vary depending on the size, width, and length of the name.</li>
            <li><span></span>Custom orders typically require approximately 3-6 weeks for production. Expedited service is available.</li>
        </ul>
        </div>
            <quantity-counter>
                <div class="quantity-selector">
                <label for="product-quantity-buttons" class="label-hidden">Quantity</label>
                <button class="quantity__minus" type="button" name="decrease" title="Decrease button quantity"><svg
                    aria-hidden="true" focusable="false" role="presentation" class="icon icon-minus" viewBox="0 0 24 24">
                    <path d="M6 12h12" stroke="#000" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg></button>
                <input id="product-quantity-buttons" class="quantity__input" type="number" name="quantity" value="1" min="1"
                    aria-label="quantity" autocomplete="off" title="Quantity field" pattern="[0-9]*" data-popout-input="">
                <button class="quantity__plus" type="button" name="increase" title="Increase button quantity"><svg
                    aria-hidden="true" focusable="false" role="presentation" class="icon icon-plus" viewBox="0 0 24 24">
                    <path d="M6 12h6m6 0h-6m0 0V6m0 6v6" stroke="#000" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg></button>
                </div>
            </quantity-counter>
            <button id="drawerAddToCart" type="button" class="btn btn--solid btn--black">
                <span class="btn__text">Add to cart <span class="btn__price"></span></span>
            </button>
        </div>
        </div>
        `;
        addToCartWrapper.insertAdjacentHTML("beforebegin", drawerHTML);

        const overlay = document.getElementById("customOverlay");
        const drawer = document.getElementById("customDrawer");
        const closeBtn = document.getElementById("drawerClose");
        const drawerBtn = document.getElementById("drawerAddToCart");

        overlay.addEventListener("click", () => {
            drawer.classList.remove("open");
            overlay.classList.remove("show");
            body.classList.remove("no-scroll");
            setTimeout(() => {
                body.classList.remove("customizeropen");
            }, 400)
        });
        closeBtn.addEventListener("click", () => {
            drawer.classList.remove("open");
            overlay.classList.remove("show");
            body.classList.remove("no-scroll");
            setTimeout(() => {
                body.classList.remove("customizeropen");
            }, 400)
        });

        drawerBtn.addEventListener("click", () => {
            if (addToCartButton) addToCartButton.click();
        });
        document.addEventListener("theme:product:add", () => {
            setTimeout(() => {
                if (drawer) drawer.classList.remove("open");
                if (overlay) overlay.classList.remove("show");
                body.classList.remove("no-scroll");
                setTimeout(() => {
                    body.classList.remove("customizeropen");
                }, 400);
            }, 500);
        });
        document.addEventListener('change', function (e) {
            const metalSelect = e.target.closest('[name="properties[Metal]"]');
            if (!metalSelect) return;

            const selectedValue = metalSelect.value.trim();
            if (!selectedValue) return;

            const zoomContainer = document.querySelector('zoom-images');
            if (!zoomContainer) return;

            const match = zoomContainer.querySelector(`[data-alt="${selectedValue}"]`);
            const match2 = document.querySelector(`product-component [data-thumbalt="${selectedValue}"]`);
            if (!match) return;

            // Always trigger click
            if (match2) match2.click();

            // Skip swap on mobile (<= 749px)
            if (window.innerWidth <= 749) return;

            const firstSlide = zoomContainer.querySelector('[data-order="0"]');
            if (!firstSlide) return;

            const firstOrder = firstSlide.style.order;
            const matchOrder = match.style.order;

            firstSlide.style.order = matchOrder;
            match.style.order = firstOrder;

            firstSlide.dataset.order = matchOrder;
            match.dataset.order = firstOrder;
        });

    }
    function initCustomSelects() {
        document.querySelectorAll('.custom-select').forEach(custom => {
            if (custom.dataset.initialized) return;
            custom.dataset.initialized = 'true';

            const wrap = custom.closest('.custom-select-wrap');
            const real = wrap.querySelector('.custom-select-hidden');
            const selected = custom.querySelector('.selected');
            const options = custom.querySelector('.options');
            const optionDivs = custom.querySelectorAll('.option');

            // Set default from real select
            const defaultOption = custom.querySelector('.option.active');
            if (defaultOption) {
                selected.innerHTML = defaultOption.innerHTML;
                real.value = defaultOption.dataset.value;
            }

            selected.addEventListener('click', e => {
                e.stopPropagation();
                const isOpen = options.style.display === 'block';
                document.querySelectorAll('.custom-select .options').forEach(opt => opt.style.display = 'none');
                options.style.display = isOpen ? 'none' : 'block';
            });

            optionDivs.forEach(opt => {
                opt.addEventListener('click', function () {
                    optionDivs.forEach(o => o.classList.remove('active'));
                    this.classList.add('active');
                    selected.innerHTML = this.innerHTML;
                    options.style.display = 'none';
                    real.value = this.dataset.value;
                    real.dispatchEvent(new Event('change', { bubbles: true }));
                });
            });

            document.addEventListener('click', e => {
                if (!custom.contains(e.target)) options.style.display = 'none';
            });
        });
    }

    if (window.location.pathname.includes("/products/")) {
        // const urlParts = window.location.pathname.split("/");
        // const productHandle = urlParts[urlParts.indexOf("products") + 1] || "";
        const productId = addToCartButton.closest('product-form').querySelector('[name="product-id"]').value;
        if (productId) {
            addToCartButton.disabled = true;

            fetchPost(ACTIONS.GET_OPTIONS, { productId: productId, title: productTitle })
                .then(result => {
                    if (!addToCartWrapper) return;

                    if (result.form) {
                        const rawCount = result.options_count;
                        const optionsCount = typeof rawCount === 'number' ? rawCount : parseInt(rawCount, 10);
                        const useInline = !isNaN(optionsCount) && optionsCount <= 4;

                        if (useInline) {
                            const styleEl = document.createElement("style");
                            styleEl.id = "custom-product-options-inline-styles";
                            styleEl.textContent = INLINE_OPTIONS_CSS;
                            if (!document.getElementById(styleEl.id)) document.head.appendChild(styleEl);

                            const inlineWrap = document.createElement("div");
                            inlineWrap.className = "custom-product-options-inline-wrap";
                            inlineWrap.innerHTML = result.form;
                            addToCartWrapper.insertAdjacentElement("beforebegin", inlineWrap);

                            bindOptionEvents(inlineWrap);
                            const optionsParent = inlineWrap.querySelector('[data-customproductoptions]');
                            if (optionsParent) {
                                const pid = optionsParent.dataset.productid;
                                const productPrice = productPriceEl.dataset.price;
                                const options = collectOptions(optionsParent);
                                updatePrice(pid, productPrice, options);
                            }
                            initCustomSelects();
                        } else {
                            createDrawer(addToCartWrapper, productComponent);

                            const drawerToggle = document.createElement("button");
                            drawerToggle.innerText = "Customize Product";
                            drawerToggle.className = "btn btn--solid btn--black";
                            drawerToggle.type = "button";
                            const drawerContent = document.getElementById("drawerContent");
                            if (!drawerContent) return;

                            drawerContent.innerHTML = result.form;

                            bindOptionEvents(drawerContent);

                            const optionsParent = drawerContent.querySelector('[data-customproductoptions]');
                            if (optionsParent) {
                                const pid = optionsParent.dataset.productid;
                                const productPrice = productPriceEl.dataset.price;
                                const options = collectOptions(optionsParent);
                                updatePrice(pid, productPrice, options);
                            }
                            drawerToggle.addEventListener("click", () => {
                                document.getElementById("customDrawer").classList.add("open");
                                document.getElementById("customOverlay").classList.add("show");
                                body.classList.add("customizeropen", "no-scroll");
                            });
                            initCustomSelects();
                            addToCartButton.style.display = "none";
                            addToCartQuantity.remove();
                            addToCartWrapper.insertAdjacentElement("beforeend", drawerToggle);
                        }
                        addToCartButton.disabled = false;
                    } else {
                        addToCartButton.disabled = false;
                    }
                })
                .catch(console.error);
        }
    }
})();
