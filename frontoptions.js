(function () {
	const ENDPOINT = `https://apps.royalhawaiianheritage.com/process.php?domain=${Shopify.shop}`;
    const INVENTORY_ENDPOINT = 'https://apps.royalhawaiianheritage.com/validate-option-inventory.php';

    async function loadEngravingInstructions() {
        const accordion = document.querySelector('.engraving-instructions');
        if (!accordion) return;

        try {
            const response = await fetch(ENDPOINT, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'get_engraving_instructions' })
            });
            const result = await response.json();
            if (!result.success || !result.data) return;

            const title = accordion.querySelector('.engraving-instructions-trigger span');
            const content = accordion.querySelector('.engraving-instructions-content');
            if (title) title.textContent = result.data.title;
            if (content) {
                content.innerHTML = result.data.content_html;
                content.querySelectorAll('ul').forEach(list => list.classList.add('engraving-instruction-list'));
                content.querySelectorAll('ul li').forEach(item => {
                    if (!item.querySelector(':scope > span')) item.prepend(document.createElement('span'));
                });
            }
        } catch (error) {
            console.warn('Unable to load engraving instructions.', error);
        }
    }
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

    function syncOptionValueIds(optionsParent) {
        const ids = collectOptions(optionsParent).map(option => option.option_value_id).filter(Boolean);
        const form = addToCartButton.closest('form');
        if (!form) return;
        let field = form.querySelector('input[name="properties[_Option Value IDs]"]');
        if (!field) {
            field = document.createElement('input');
            field.type = 'hidden';
            field.name = 'properties[_Option Value IDs]';
            form.appendChild(field);
        }
        field.value = [...new Set(ids)].join(',');
    }

    function handleOptionChange(e) {
        const optionsParent = e.target.closest('[data-customproductoptions]');
        if (!optionsParent) return;
        const productId = optionsParent.dataset.productid;
        // const productPrice = optionsParent.dataset.productprice;
        const productPrice = productPriceEl.dataset.price;
        const options = collectOptions(optionsParent);
        syncOptionValueIds(optionsParent);
        updatePrice(productId, productPrice, options);
    }

    function bindOptionEvents(scope) {
        const optionsParent = scope.querySelector('[data-customproductoptions]');
        if (!optionsParent) return;
        optionsParent.querySelectorAll('select, [type="radio"]').forEach(el => {
            el.addEventListener('change', handleOptionChange);
        });
        syncOptionValueIds(optionsParent);
        applyInventoryAvailability(optionsParent);
    }

    async function applyInventoryAvailability(optionsParent) {
        const ids = [...new Set(Array.from(optionsParent.querySelectorAll('[data-optionvalueid]')).map(el => el.dataset.optionvalueid).filter(Boolean))];
        if (!ids.length) return;
        try {
            const response = await fetch(INVENTORY_ENDPOINT, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({shop:Shopify.shop, availability_check:true, lines:ids.map(id => ({key:'option-'+id, quantity:1, option_value_ids:[id]}))})});
            const result = await response.json();
            const unavailable = new Map((result.errors || []).map(error => [String(error.option_value_id), error.message]));
            unavailable.forEach((message, id) => {
                optionsParent.querySelectorAll(`[data-optionvalueid="${CSS.escape(id)}"]`).forEach(el => {
                    if (el.matches('input,option')) el.disabled = true;
                    el.dataset.inventoryDisabled = '1'; el.title = message;
                    if (el.matches('.option')) { el.style.opacity = '.35'; el.style.pointerEvents = 'none'; }
                    if (el.matches('input[type="radio"]')) {
                        el.style.opacity = '0';
                        const label = optionsParent.querySelector(`label[for="${CSS.escape(el.id)}"]`);
                        if (label) { label.style.opacity='.35'; label.style.cursor='not-allowed'; label.title=message; }
                        if (el.checked) el.checked=false;
                    }
                });
            });
            syncOptionValueIds(optionsParent);
        } catch (error) { console.warn('Unable to refresh connected option inventory.', error); }
    }

    const INLINE_OPTIONS_CSS = `
    .custom-product-options-inline-wrap .custom-product-options [type=radio]+label{display:flex;align-items:center;justify-content:center;flex-direction:column;flex:0 0 calc(100% / 4 - 15px / 4);gap:10px;padding:10px;border:1px solid #ccc;font-size:14px;color:#000;transition:.3s;cursor:pointer;text-align:center;border-radius:5px}
    .custom-product-options-inline-wrap .custom-product-options>div{width:100%;display:flex;flex-wrap:wrap;gap:10px 5px;}
    .custom-product-options-inline-wrap .custom-product-options>div legend{flex:0 0 100%;color:#000;font-size:16px;padding:0}
    .custom-product-options-inline-wrap .custom-product-options [type=radio]:checked+label{border-color:#000}
    .custom-product-options-inline-wrap .custom-product-options input[type=text],
    .custom-product-options-inline-wrap .custom-product-options select{color:#000;font-size:14px;padding:10px;border:1px solid #000;display:block;width:100%;margin:0}
    .custom-product-options-inline-wrap .custom-product-options [type=radio]+label{}
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
    .custom-product-options [type=radio]{opacity:0;pointer-events:none;position:absolute;left:0;top:0;width:100%;height:100%}.custom-product-options>div{position:relative}
    `;

    function createDrawer(addToCartWrapper, productComponent) {
        if (document.getElementById("customDrawer")) return;

        const drawerHTML = `
        <style>
        .custom-drawer,.custom-overlay{position:fixed;top:0;height:100%}.custom-overlay{left:0;width:100%;background:rgb(0 0 0 / .5);z-index:999;display:none}.custom-overlay.show,.drawer-close{display:block}.custom-drawer{right:0;width:520px;background:#fff;box-shadow:-2px 0 5px rgb(0 0 0 / .3);z-index:999999999;padding:20px;transition:.3s;transform:translateX(100%)}.custom-drawer.open{transform:translateX(0)}.drawer-close{background:0 0;border:none;cursor:pointer;outline:0;padding:0;width:20px;height:20px}.custom-drawer-atc_btnwpr{display:flex;gap:5px}#drawerAddToCart{width:calc(100% - 105px)}body.customizeropen main#MainContent{position:relative;z-index:9999999}#drawerContent{overflow-y:auto;height:calc(100% - 200px);width:100%;scrollbar-width:none;scrollbar-width:thin;scrollbar-color:#000 #f5f5f5;padding-right:10px;transition:all 0.4s}div#customDrawer:has(.engraving-instructions.active) div#drawerContent{height:calc(100% - 385px)}.custom-product-options [type=radio]+label{display:flex;align-items:center;justify-content:center;flex-direction:column;flex:0 0 calc(100% / 4 - 15px / 4);gap:10px;padding:10px;border:1px solid #ccc;font-size:14px;color:#000;transition:.3s;cursor:pointer;text-align:center;border-radius:5px}.custom-product-options>div{width:100%;display:flex;flex-wrap:wrap;gap:10px 5px}.custom-product-options>div legend{flex:0 0 100%;color:#000;font-size:16px;padding:0}.custom-drawer-footer .quantity-selector *,.custom-product-options [type=radio]:checked+label{border-color:#000}.custom-product-options input[type=text],.custom-product-options select{color:#000;font-size:14px;padding:10px;border:1px solid #000;display:block;width:100%;margin:0}span#drawerPrice{font-size:18px;color:#000;letter-spacing:.5px;line-height:1}.custom-drawer-header{display:flex;align-items:center;justify-content:space-between;padding-bottom:15px;margin-bottom:15px;border-bottom:1px solid #000}.drawer-close svg{transform:rotate(45deg);width:20px;height:20px;display:block}.custom-drawer-footer quantity-counter{width:100px}.custom-drawer-footer{padding-top:15px;margin-top:15px;border-top:1px solid #000}body.customizeropen .product__wrapper{position:relative;z-index:999999}#drawerContent::-webkit-scrollbar{width:8px}#drawerContent::-webkit-scrollbar-track{background:#f5f5f5;border-radius:4px}#drawerContent::-webkit-scrollbar-thumb{background-color:#000;border-radius:4px;border:1px solid #f5f5f5}#drawerContent::-webkit-scrollbar-thumb:hover{background-color:#444}.engraving-instructions>p{margin-top:0}ul.engraving-instruction-list{margin:0;list-style:none;padding:0;color:#000;font-size:14px}.engraving-instruction-list li span{display:block;width:5px;height:5px;background-color:#000;border-radius:50%;min-width:5px}.engraving-instruction-list li{display:flex;align-items:center;gap:10px;line-height:1.2}@media only screen and (max-width:767px){.custom-drawer{max-width:100%;width:400px}}.no-scroll{overflow:hidden}.custom-product-options [type=radio]+label img{width:30px;height:30px;object-fit:cover;border-radius:50%;display:block}.custom-select-wrap{position:relative;display:inline-block;width:100%}.custom-select-hidden{position:absolute;left:0;top:0;width:100%;height:100%;opacity:0;pointer-events:none;z-index:-1}.custom-select{position:relative}.custom-select .selected{border:1px solid #ccc;padding:8px;cursor:pointer;display:flex;align-items:center;gap:8px;background:#fff;user-select:none}.custom-select .options{position:absolute;width:100%;border:1px solid #ccc;border-top:none;background:#fff;display:none;z-index:20;max-height:180px;overflow-y:auto}.custom-select .option{padding:8px;cursor:pointer;display:flex;align-items:center;gap:8px}.custom-select .option:hover{background:#f0f0f0}.custom-select .option.active{background:#f5f5f5;font-weight:600}.custom-select img{width:24px;height:24px;object-fit:cover}.custom-product-options [type=radio]+label{text-transform:capitalize}.engraving-instructions{margin-bottom:15px;background-color:#fff0;width:100%;padding:0 0 15px;font-size:14px;color:#000;border-bottom:1px solid #000;border-radius:0}.engraving-instructions-trigger{display:flex;align-items:center;justify-content:space-between;width:100%;line-height:1;padding:0}#drawerAddToCart{position:relative}#drawerAddToCart.btn--loading{opacity:.85;pointer-events:none}@keyframes btn-spin{to{transform:rotate(360deg)}}
        .custom-product-options [type=radio]{opacity:0;pointer-events:none;position:absolute;left:0;top:0;width:100%;height:100%}.custom-product-options>div{position:relative}
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
        <button class="engraving-instructions-trigger" type="button">
            <span>Engraving & Customization Terms</span>
            <svg aria-hidden="true" focusable="false" role="presentation" class="icon icon-nav-arrow-down" viewBox="0 0 24 24">
                <path d="m6 9 6 6 6-6" stroke="#000" stroke-linecap="round" stroke-linejoin="round"></path>
            </svg>
        </button>
        <div class="engraving-instructions-content">
        <p class="engraving-instruction-text">I understand that all custom items are made especially for me, are final sale, and that:</p>
                <ul class="engraving-instruction-list">
                    <li><span></span>Orders are not eligible for exchanges, refunds, or cancellations.</li>
                    <li><span></span>No changes can be made once the order has been submitted.</li>
                    <li><span></span>Due to the handmade process, engraving may vary slightly from samples and previews.</li>
                    <li><span></span>Lettering may vary depending on the size, width, and length of the name.</li>
                    <li><span></span>Custom orders typically require approximately 3-6 weeks for production. Expedited service is available.</li>
                </ul>
        </div>
        </div>
        <div class="custom-drawer-atc_btnwpr">
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
            if (!addToCartButton) return;
            // Check form validity before showing loader
            const form = addToCartButton.closest('form');
            if (form && !form.checkValidity()) {
                form.reportValidity();
                return;
            }
            drawerBtn.disabled = true;
            drawerBtn.classList.add("btn--loading");
            drawerBtn.querySelector(".btn__text").style.visibility = "hidden";
            if (!drawerBtn.querySelector(".btn-spinner")) {
                drawerBtn.insertAdjacentHTML("beforeend",
                    '<span class="btn-spinner" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;">' +
                    '<svg width="20" height="20" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="animation:btn-spin .6s linear infinite">' +
                    '<circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2.5" stroke-dasharray="50 20" stroke-linecap="round"/>' +
                    '</svg></span>'
                );
            }
            addToCartButton.click();
        });
        document.addEventListener("theme:product:add", () => {
            drawerBtn.disabled = false;
            drawerBtn.classList.remove("btn--loading");
            drawerBtn.querySelector(".btn__text").style.visibility = "";
            const spinner = drawerBtn.querySelector(".btn-spinner");
            if (spinner) spinner.remove();
            // Show brief checkmark before closing
            drawerBtn.insertAdjacentHTML("beforeend",
                '<span class="btn-added" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;">' +
                '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">' +
                '<path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>' +
                '</svg></span>'
            );
            drawerBtn.querySelector(".btn__text").style.visibility = "hidden";
            setTimeout(() => {
                const added = drawerBtn.querySelector(".btn-added");
                if (added) added.remove();
                drawerBtn.querySelector(".btn__text").style.visibility = "";
                if (drawer) drawer.classList.remove("open");
                if (overlay) overlay.classList.remove("show");
                body.classList.remove("no-scroll");
                setTimeout(() => {
                    body.classList.remove("customizeropen");
                }, 400);
            }, 800);
        });
        document.addEventListener("theme:product:add-error", () => {
            drawerBtn.disabled = false;
            drawerBtn.classList.remove("btn--loading");
            drawerBtn.querySelector(".btn__text").style.visibility = "";
            const spinner = drawerBtn.querySelector(".btn-spinner");
            if (spinner) spinner.remove();
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
    function engravingInstructions() {
        var accordion = document.querySelector('.engraving-instructions');
        if (!accordion) return;

        var trigger = accordion.querySelector('.engraving-instructions-trigger');
        var content = accordion.querySelector('.engraving-instructions-content');

        content.style.height = '0px';
        content.style.overflow = 'hidden';
        content.style.transition = 'height 0.3s ease';

        trigger.addEventListener('click', function () {
            var isOpen = accordion.classList.contains('active');

            if (isOpen) {
                content.style.height = content.scrollHeight + 'px';
                requestAnimationFrame(function () {
                    content.style.height = '0px';
                });
                accordion.classList.remove('active');
            } else {
                content.style.height = content.scrollHeight + 'px';
                accordion.classList.add('active');
            }
        });

        content.addEventListener('transitionend', function () {
            if (accordion.classList.contains('active')) {
                content.style.height = 'auto';
            }
        });

    }
    // ─── Conditional Option Logic ────────────────────────────────────────────
    //
    // Conditions are evaluated after every option change.
    // Each rule has:
    //   triggers  – array of { opt: RegExp (matches legend text), val?: RegExp, valTest?: fn }
    //               ALL triggers must match (AND logic).
    //   restrict  – { opt: RegExp, allow?: [RegExp], block?: [RegExp], allowTest?: fn }
    //               Disables values in the target option that don't match `allow`,
    //               or that match `block`, or that fail `allowTest`.
    //   message   – tooltip shown on disabled values.
    //
    const OPTION_CONDITIONS = [
        {
            // Width = 4mm → Edging: only Smooth or Scalloped
            triggers: [{ opt: /^width$/i, val: /\b4\s*mm\b/i }],
            restrict: { opt: /^edging$/i, allow: [/smooth/i, /scalloped/i] },
            message: '4mm is only available with Smooth or Scalloped edging'
        },
        {
            // Lettering Type = any "Raised" variant → Lettering Background: Sand only
            triggers: [{ opt: /lettering\s*type/i, val: /raised/i }],
            restrict: { opt: /lettering\s*background|background/i, allow: [/sand/i] },
            message: 'Raised lettering is only available with Sand background'
        },
        {
            // Width < 10mm → Specific scroll patterns/designs are blocked
            triggers: [{
                opt: /^width$/i,
                valTest: v => { const n = parseFloat(v); return !isNaN(n) && n < 10; }
            }],
            restrict: {
                opt: /scroll\s*pattern/i,
                block: [/garden\s*of\s*eden|playful\s*sea\s*turtles|playful\s*dolphins|royal\s*bamboo|royal\s*hula|royal\s*kahiko|aloha\s*hula/i]
            },
            message: 'This design is only available in 10mm width or wider'
        },
        {
            // Diamonds / Add Birthstone = 1/4ct → Diamond Shape: Round only
            triggers: [{ opt: /diamonds?|birthstone/i, val: /1\s*\/\s*4\s*ct/i }],
            restrict: { opt: /diamond\s*shape|shape/i, allow: [/round/i] },
            message: '1/4ct diamonds are only available in Round shape'
        },
        {
            // Metal = Sterling Silver → Weight: Heavy only (not Extra Heavy)
            triggers: [{ opt: /^metal$|precious\s*metal/i, val: /sterling\s*silver/i }],
            restrict: { opt: /^weight$/i, allow: [/^heavy$/i] },
            message: 'Sterling Silver is only available in Heavy weight'
        },
        {
            // Width outside 4mm–12mm → Weight: Light is unavailable (14K & 18K rule)
            triggers: [{
                opt: /^width$/i,
                valTest: v => { const n = parseFloat(v); return !isNaN(n) && (n < 4 || n > 12); }
            }],
            restrict: { opt: /^weight$/i, block: [/^light$/i] },
            message: 'Light weight is only available for 4mm–12mm widths'
        }
    ];

    function initConditionalLogic(scope) {
        const wrapper = scope.querySelector('[data-customproductoptions]') || scope;
        let _lock = false;

        function legendText(group) {
            const el = group.querySelector('legend');
            return el ? el.textContent.replace(/[*:\s]+$/, '').trim() : '';
        }

        function allGroups() {
            return Array.from(wrapper.querySelectorAll('.option-values-wp'));
        }

        function selectedText(group) {
            const checked = group.querySelector('input[type="radio"]:checked');
            if (checked) return (checked.value || '').trim();
            const sel = group.querySelector('.custom-select-hidden');
            return sel ? (sel.value || '').trim() : '';
        }

        function optionValues(group) {
            const vals = [];
            group.querySelectorAll('input[type="radio"]').forEach(r => { if (r.value) vals.push(r.value.trim()); });
            const sel = group.querySelector('.custom-select-hidden');
            if (sel) Array.from(sel.options).forEach(o => { if (o.value) vals.push(o.value.trim()); });
            return [...new Set(vals)];
        }

        function setDisabled(group, val, disabled, msg) {
            const lc = val.toLowerCase();
            // Radios
            group.querySelectorAll('input[type="radio"]').forEach(r => {
                if ((r.value || '').trim().toLowerCase() !== lc) return;
                r.disabled = disabled;
                const label = group.querySelector('label[for="' + r.id + '"]');
                if (label) {
                    label.style.opacity = disabled ? '0.35' : '';
                    label.style.cursor  = disabled ? 'not-allowed' : '';
                    label.title         = disabled ? (msg || '') : '';
                }
            });
            // Real <select> option
            const realSel = group.querySelector('.custom-select-hidden');
            if (realSel) {
                Array.from(realSel.options).forEach(o => {
                    if ((o.value || '').trim().toLowerCase() === lc) o.disabled = disabled;
                });
            }
            // Visual custom-select option div
            const customSel = group.querySelector('.custom-select');
            if (customSel) {
                customSel.querySelectorAll('.option').forEach(div => {
                    const t = (div.querySelector('span') || div).textContent.trim().toLowerCase();
                    if (t !== lc) return;
                    if (disabled) {
                        div.style.opacity       = '0.35';
                        div.style.pointerEvents = 'none';
                        div.style.cursor        = 'not-allowed';
                        div.title               = msg || '';
                        div.dataset.condDisabled = '1';
                    } else {
                        div.style.opacity       = '';
                        div.style.pointerEvents = '';
                        div.style.cursor        = '';
                        div.title               = '';
                        delete div.dataset.condDisabled;
                    }
                });
            }
        }

        function fixSelection(group) {
            // If the currently selected value was just disabled, pick the first enabled one
            const radios = Array.from(group.querySelectorAll('input[type="radio"]'));
            if (radios.length) {
                const checked = group.querySelector('input[type="radio"]:checked');
                if (checked && checked.disabled) {
                    checked.checked = false;
                    const first = radios.find(r => !r.disabled);
                    if (first) { first.checked = true; first.dispatchEvent(new Event('change', { bubbles: true })); }
                }
                return;
            }
            const realSel  = group.querySelector('.custom-select-hidden');
            const customSel = group.querySelector('.custom-select');
            if (!realSel || !realSel.value) return;
            const cur = realSel.options[realSel.selectedIndex];
            if (!cur || !cur.disabled) return;
            const first = Array.from(realSel.options).find(o => o.value && !o.disabled);
            if (!first) return;
            realSel.value = first.value;
            if (customSel) {
                const selDiv   = customSel.querySelector('.selected');
                const firstOpt = Array.from(customSel.querySelectorAll('.option')).find(d => !d.dataset.condDisabled);
                if (selDiv && firstOpt) {
                    selDiv.innerHTML = firstOpt.innerHTML;
                    customSel.querySelectorAll('.option').forEach(d => d.classList.remove('active'));
                    firstOpt.classList.add('active');
                }
            }
            realSel.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function applyConditions() {
            if (_lock) return;
            _lock = true;
            try {
                const groups = allGroups();

                // 1. Reset all previously condition-disabled states
                groups.forEach(group => {
                    group.querySelectorAll('input[type="radio"]').forEach(r => {
                        r.disabled = false;
                        const label = group.querySelector('label[for="' + r.id + '"]');
                        if (label) { label.style.opacity = ''; label.style.cursor = ''; label.title = ''; }
                    });
                    const realSel = group.querySelector('.custom-select-hidden');
                    if (realSel) Array.from(realSel.options).forEach(o => { if (o.value) o.disabled = false; });
                    const customSel = group.querySelector('.custom-select');
                    if (customSel) {
                        customSel.querySelectorAll('.option[data-cond-disabled]').forEach(d => {
                            d.style.opacity = ''; d.style.pointerEvents = ''; d.style.cursor = ''; d.title = '';
                            delete d.dataset.condDisabled;
                        });
                    }
                });

                // 2. Build current selection map  { "Metal": "14K Yellow Gold", ... }
                const sel = {};
                groups.forEach(g => { const n = legendText(g); if (n) sel[n] = selectedText(g); });

                // 3. Evaluate each condition
                OPTION_CONDITIONS.forEach(cond => {
                    if (!cond.triggers || !cond.restrict) return;

                    const triggered = cond.triggers.every(t =>
                        Object.entries(sel).some(([name, val]) => {
                            if (!t.opt.test(name)) return false;
                            if (t.valTest) return t.valTest(val);
                            if (t.val)     return t.val.test(val);
                            return false;
                        })
                    );
                    if (!triggered) return;

                    const targetGroup = groups.find(g => cond.restrict.opt.test(legendText(g)));
                    if (!targetGroup) return;

                    optionValues(targetGroup).forEach(val => {
                        let disable = false;
                        if (cond.restrict.allow)     disable = !cond.restrict.allow.some(p => p.test(val));
                        else if (cond.restrict.block) disable =  cond.restrict.block.some(p => p.test(val));
                        else if (cond.restrict.allowTest) disable = !cond.restrict.allowTest(val);
                        if (disable) setDisabled(targetGroup, val, true, cond.message || '');
                    });

                    fixSelection(targetGroup);
                });
            } finally {
                _lock = false;
            }
        }

        wrapper.addEventListener('change', () => setTimeout(applyConditions, 0));
        applyConditions();
    }
    // ─── End Conditional Option Logic ────────────────────────────────────────

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
                            initConditionalLogic(inlineWrap);
                        } else {
                            createDrawer(addToCartWrapper, productComponent);

                            const drawerToggle = document.createElement("button");
                            drawerToggle.innerText = "Customize Product";
                            drawerToggle.className = "btn btn--solid btn--black";
                            drawerToggle.type = "button";
                            const drawerContent = document.getElementById("drawerContent");
                            if (!drawerContent) return;
                            loadEngravingInstructions();
                            engravingInstructions();
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
                            initConditionalLogic(drawerContent);
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
