(function () {
    const boot = window.CUSTOM_ORDER_BOOT;
    if (!boot || !boot.product) return;

    const product = boot.product;
    const form = document.getElementById('customOrderForm');
    const variantMount = document.getElementById('variantOptions');
    const optionsMount = document.getElementById('customOptionsMount');
    const priceEl = document.getElementById('productPriceDisplay');
    const priceBtn = document.querySelector('#createDraftOrder .btn__price');
    const skuEl = document.getElementById('variantSku');
    const mainImage = document.getElementById('mainProductImage');
    const thumbs = document.getElementById('productThumbs');
    const statusEl = document.getElementById('orderStatus');
    const submitBtn = document.getElementById('createDraftOrder');
    const qtyInput = document.getElementById('orderQuantity');

    let selectedVariant = product.variants[0] || null;
    let lastRawPrice = selectedVariant ? Number(selectedVariant.price) : 0;

    const OPTION_CONDITIONS = [
        {
            triggers: [{ opt: /^width$/i, val: /\b4\s*mm\b/i }],
            restrict: { opt: /^edging$/i, allow: [/smooth/i, /scalloped/i] },
            message: '4mm is only available with Smooth or Scalloped edging'
        },
        {
            triggers: [{ opt: /lettering\s*type/i, val: /raised/i }],
            restrict: { opt: /lettering\s*background|background/i, allow: [/sand/i] },
            message: 'Raised lettering is only available with Sand background'
        },
        {
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
            triggers: [{ opt: /diamonds?|birthstone/i, val: /1\s*\/\s*4\s*ct/i }],
            restrict: { opt: /diamond\s*shape|shape/i, allow: [/round/i] },
            message: '1/4ct diamonds are only available in Round shape'
        },
        {
            triggers: [{ opt: /^metal$|precious\s*metal/i, val: /sterling\s*silver/i }],
            restrict: { opt: /^weight$/i, allow: [/^heavy$/i] },
            message: 'Sterling Silver is only available in Heavy weight'
        },
        {
            triggers: [{
                opt: /^width$/i,
                valTest: v => { const n = parseFloat(v); return !isNaN(n) && (n < 4 || n > 12); }
            }],
            restrict: { opt: /^weight$/i, block: [/^light$/i] },
            message: 'Light weight is only available for 4mm–12mm widths'
        }
    ];

    function money(amount) {
        const value = Number(amount);
        try {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: boot.currency || 'USD'
            }).format(isNaN(value) ? 0 : value);
        } catch (e) {
            return '$' + (isNaN(value) ? '0.00' : value.toFixed(2));
        }
    }

    function fetchPost(action, payload) {
        const formData = new URLSearchParams({ action, ...payload });
        return fetch(boot.endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        }).then(res => res.json());
    }

    function setMainImage(url, alt) {
        if (!mainImage || !url) return;
        mainImage.src = url;
        if (alt) mainImage.alt = alt;
        if (!thumbs) return;
        thumbs.querySelectorAll('button').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.url === url);
        });
    }

    function renderThumbs() {
        if (!thumbs) return;
        const images = product.images && product.images.length
            ? product.images
            : (product.featuredImage ? [{ url: product.featuredImage, alt: product.title }] : []);
        thumbs.innerHTML = images.map((img, index) => (
            '<button type="button" class="' + (index === 0 ? 'active' : '') + '" data-url="' + encodeURI(img.url) + '">' +
            '<img src="' + encodeURI(img.url) + '" alt="' + (img.alt || product.title).replace(/"/g, '&quot;') + '">' +
            '</button>'
        )).join('');
        thumbs.querySelectorAll('button').forEach(btn => {
            btn.addEventListener('click', () => setMainImage(btn.dataset.url, product.title));
        });
    }

    function optionSlug(name, value, index) {
        return String(name + '_' + value + '_' + index).toLowerCase().replace(/[^a-z0-9_]+/g, '_');
    }

    function currentVariantSelection() {
        const selected = {};
        variantMount.querySelectorAll('.variant-group').forEach(group => {
            const checked = group.querySelector('input[type="radio"]:checked');
            if (checked) selected[group.dataset.optionName] = checked.value;
        });
        return selected;
    }

    function findVariant(selected) {
        return product.variants.find(variant =>
            (variant.selectedOptions || []).every(opt => selected[opt.name] === opt.value)
        ) || null;
    }

    function availableValues(optionName) {
        const selected = currentVariantSelection();
        const values = new Set();
        product.variants.forEach(variant => {
            const matches = (variant.selectedOptions || []).every(opt => {
                if (opt.name === optionName) return true;
                return !selected[opt.name] || selected[opt.name] === opt.value;
            });
            if (!matches) return;
            const own = (variant.selectedOptions || []).find(opt => opt.name === optionName);
            if (own) values.add(own.value);
        });
        return values;
    }

    function applyVariantAvailability() {
        variantMount.querySelectorAll('.variant-group').forEach(group => {
            const name = group.dataset.optionName;
            const allowed = availableValues(name);
            group.querySelectorAll('input[type="radio"]').forEach(input => {
                const ok = allowed.has(input.value);
                input.disabled = !ok;
                const label = group.querySelector('label[for="' + input.id + '"]');
                if (label) {
                    label.style.opacity = ok ? '' : '0.35';
                    label.style.cursor = ok ? '' : 'not-allowed';
                    label.title = ok ? '' : 'Not available with the current combination';
                }
                if (!ok && input.checked) {
                    const first = Array.from(group.querySelectorAll('input[type="radio"]')).find(r => allowed.has(r.value));
                    if (first) first.checked = true;
                }
            });
        });
    }

    function selectVariant(variant, updateImage) {
        selectedVariant = variant;
        if (!variant) {
            skuEl.textContent = 'This combination is unavailable.';
            submitBtn.disabled = true;
            return;
        }
        submitBtn.disabled = false;
        skuEl.textContent = variant.sku ? ('SKU: ' + variant.sku) : '';
        if (updateImage && variant.image) setMainImage(variant.image, product.title);
        refreshPrice();
    }

    function renderVariants() {
        if (!product.options.length) {
            variantMount.innerHTML = '';
            selectedVariant = product.variants[0] || null;
            return;
        }
        const initial = (product.variants[0] && product.variants[0].selectedOptions) || [];
        const initialMap = {};
        initial.forEach(opt => { initialMap[opt.name] = opt.value; });

        variantMount.innerHTML = product.options.map(option => {
            const radios = option.values.map((value, index) => {
                const id = optionSlug(option.name, value, index);
                const checked = initialMap[option.name] === value ? ' checked' : '';
                return '<input type="radio" id="' + id + '" name="variant[' + option.name.replace(/"/g, '&quot;') + ']" value="' + String(value).replace(/"/g, '&quot;') + '"' + checked + '>' +
                    '<label for="' + id + '"><span>' + value + '</span></label>';
            }).join('');
            return '<div class="variant-group custom-product-options" data-option-name="' + option.name.replace(/"/g, '&quot;') + '">' +
                '<legend><strong>' + option.name + ':</strong></legend>' + radios + '</div>';
        }).join('');

        variantMount.addEventListener('change', () => {
            applyVariantAvailability();
            selectVariant(findVariant(currentVariantSelection()), true);
        });
        applyVariantAvailability();
        selectVariant(findVariant(currentVariantSelection()), false);
    }

    function collectOptions(scope) {
        if (!scope) return [];
        return [
            ...scope.querySelectorAll('select'),
            ...scope.querySelectorAll('[type="radio"]:checked')
        ].map(el => {
            if (el.tagName === 'SELECT') {
                const selected = el.options[el.selectedIndex];
                return selected && selected.value
                    ? { product_option_id: selected.dataset.productoptionid, option_value_id: selected.dataset.optionvalueid }
                    : null;
            }
            return el.dataset.productoptionid && el.dataset.optionvalueid
                ? { product_option_id: el.dataset.productoptionid, option_value_id: el.dataset.optionvalueid }
                : null;
        }).filter(Boolean);
    }

    function collectProperties() {
        const props = [];
        const seen = new Set();
        if (selectedVariant && selectedVariant.title && selectedVariant.title !== 'Default Title') {
            props.push({ key: 'Variant', value: selectedVariant.title });
            seen.add('variant');
        }
        (selectedVariant && selectedVariant.selectedOptions || []).forEach(opt => {
            const key = String(opt.name || '').trim();
            if (!key || seen.has(key.toLowerCase())) return;
            seen.add(key.toLowerCase());
            props.push({ key, value: String(opt.value || '') });
        });
        form.querySelectorAll('input, select, textarea').forEach(el => {
            if (!el.name || el.name.indexOf('properties[') !== 0) return;
            if (el.type === 'radio' && !el.checked) return;
            if (el.disabled) return;
            const key = el.name.slice(11, -1);
            if (!key || seen.has(key.toLowerCase()) || !el.value) return;
            seen.add(key.toLowerCase());
            props.push({ key, value: String(el.value) });
        });
        return props;
    }

    function setDisplayedPrice(amount) {
        lastRawPrice = Number(amount) || 0;
        const formatted = money(lastRawPrice);
        priceEl.textContent = formatted;
        if (priceBtn) priceBtn.textContent = formatted;
    }

    function refreshPrice() {
        const optionsParent = optionsMount.querySelector('[data-customproductoptions]');
        const basePrice = selectedVariant ? selectedVariant.price : (product.localPrice || '0');
        if (!optionsParent || !product.localProductId) {
            setDisplayedPrice(basePrice);
            return;
        }
        priceEl.classList.add('is-loading');
        fetchPost('get_price', {
            product_id: String(optionsParent.dataset.productid || product.localProductId),
            product_price: String(basePrice),
            product_options: JSON.stringify(collectOptions(optionsParent))
        }).then(result => {
            setDisplayedPrice(result.raw_price != null ? result.raw_price : basePrice);
        }).catch(() => {
            setDisplayedPrice(basePrice);
        }).finally(() => {
            priceEl.classList.remove('is-loading');
        });
    }

    function initCustomSelects(scope) {
        scope.querySelectorAll('.custom-select').forEach(custom => {
            if (custom.dataset.initialized) return;
            custom.dataset.initialized = 'true';
            const wrap = custom.closest('.custom-select-wrap');
            const real = wrap.querySelector('.custom-select-hidden');
            const selected = custom.querySelector('.selected');
            const options = custom.querySelector('.options');
            const optionDivs = custom.querySelectorAll('.option');
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
                    if (this.dataset.condDisabled === '1') return;
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

    function initConditionalLogic(scope) {
        const wrapper = scope.querySelector('[data-customproductoptions]') || scope;
        let lock = false;

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
            group.querySelectorAll('input[type="radio"]').forEach(r => {
                if ((r.value || '').trim().toLowerCase() !== lc) return;
                r.disabled = disabled;
                const label = group.querySelector('label[for="' + r.id + '"]');
                if (label) {
                    label.style.opacity = disabled ? '0.35' : '';
                    label.style.cursor = disabled ? 'not-allowed' : '';
                    label.title = disabled ? (msg || '') : '';
                }
            });
            const realSel = group.querySelector('.custom-select-hidden');
            if (realSel) {
                Array.from(realSel.options).forEach(o => {
                    if ((o.value || '').trim().toLowerCase() === lc) o.disabled = disabled;
                });
            }
            const customSel = group.querySelector('.custom-select');
            if (customSel) {
                customSel.querySelectorAll('.option').forEach(div => {
                    const t = (div.querySelector('span') || div).textContent.trim().toLowerCase();
                    if (t !== lc) return;
                    if (disabled) {
                        div.style.opacity = '0.35';
                        div.style.pointerEvents = 'none';
                        div.style.cursor = 'not-allowed';
                        div.title = msg || '';
                        div.dataset.condDisabled = '1';
                    } else {
                        div.style.opacity = '';
                        div.style.pointerEvents = '';
                        div.style.cursor = '';
                        div.title = '';
                        delete div.dataset.condDisabled;
                    }
                });
            }
        }
        function fixSelection(group) {
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
            const realSel = group.querySelector('.custom-select-hidden');
            const customSel = group.querySelector('.custom-select');
            if (!realSel || !realSel.value) return;
            const cur = realSel.options[realSel.selectedIndex];
            if (!cur || !cur.disabled) return;
            const first = Array.from(realSel.options).find(o => o.value && !o.disabled);
            if (!first) return;
            realSel.value = first.value;
            if (customSel) {
                const selDiv = customSel.querySelector('.selected');
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
            if (lock) return;
            lock = true;
            try {
                const groups = allGroups();
                groups.forEach(group => {
                    group.querySelectorAll('input[type="radio"],input[type="checkbox"]').forEach(r => {
                        if (r.dataset.inventoryDisabled === '1') {
                            r.disabled = true;
                            return;
                        }
                        r.disabled = false;
                        const label = group.querySelector('label[for="' + r.id + '"]');
                        if (label) { label.style.opacity = ''; label.style.cursor = ''; label.title = ''; }
                    });
                    const realSel = group.querySelector('.custom-select-hidden');
                    if (realSel) Array.from(realSel.options).forEach(o => {
                        if (o.value && o.dataset.inventoryDisabled !== '1') o.disabled = false;
                    });
                    const customSel = group.querySelector('.custom-select');
                    if (customSel) {
                        customSel.querySelectorAll('.option[data-cond-disabled]').forEach(d => {
                            d.style.opacity = ''; d.style.pointerEvents = ''; d.style.cursor = ''; d.title = '';
                            delete d.dataset.condDisabled;
                        });
                    }
                });
                const sel = {};
                groups.forEach(g => { const n = legendText(g); if (n) sel[n] = selectedText(g); });
                OPTION_CONDITIONS.forEach(cond => {
                    if (!cond.triggers || !cond.restrict) return;
                    const triggered = cond.triggers.every(t =>
                        Object.entries(sel).some(([name, val]) => {
                            if (!t.opt.test(name)) return false;
                            if (t.valTest) return t.valTest(val);
                            if (t.val) return t.val.test(val);
                            return false;
                        })
                    );
                    if (!triggered) return;
                    const targetGroup = groups.find(g => cond.restrict.opt.test(legendText(g)));
                    if (!targetGroup) return;
                    optionValues(targetGroup).forEach(val => {
                        let disable = false;
                        if (cond.restrict.allow) disable = !cond.restrict.allow.some(p => p.test(val));
                        else if (cond.restrict.block) disable = cond.restrict.block.some(p => p.test(val));
                        else if (cond.restrict.allowTest) disable = !cond.restrict.allowTest(val);
                        if (disable) setDisabled(targetGroup, val, true, cond.message || '');
                    });
                    fixSelection(targetGroup);
                });
            } finally {
                lock = false;
            }
        }
        wrapper.addEventListener('change', () => setTimeout(applyConditions, 0));
        applyConditions();
    }

    async function applyInventoryAvailability(optionsParent) {
        const ids = [...new Set(Array.from(optionsParent.querySelectorAll('[data-optionvalueid]')).map(el => el.dataset.optionvalueid).filter(Boolean))];
        if (!ids.length) return;
        try {
            const response = await fetch(boot.inventoryEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    shop: boot.shop,
                    availability_check: true,
                    lines: ids.map(id => ({ key: 'option-' + id, quantity: 1, option_value_ids: [id] }))
                })
            });
            const result = await response.json();
            const unavailable = new Map((result.errors || []).map(error => [String(error.option_value_id), error.message]));
            unavailable.forEach((message, id) => {
                optionsParent.querySelectorAll('[data-optionvalueid="' + CSS.escape(id) + '"]').forEach(el => {
                    if (el.matches('input,option')) el.disabled = true;
                    el.dataset.inventoryDisabled = '1';
                    el.title = message;
                    if (el.matches('.option')) { el.style.opacity = '.35'; el.style.pointerEvents = 'none'; }
                    if (el.matches('input[type="radio"],input[type="checkbox"]')) {
                        if (el.matches('input[type="radio"]')) el.style.opacity = '0';
                        const label = optionsParent.querySelector('label[for="' + CSS.escape(el.id) + '"]');
                        if (label) { label.style.opacity = '.35'; label.style.cursor = 'not-allowed'; label.title = message; }
                        if (el.checked) el.checked = false;
                    }
                });
            });
        } catch (error) {
            console.warn('Unable to refresh connected option inventory.', error);
        }
    }

    function engravingInstructions() {
        const accordion = document.querySelector('.engraving-instructions');
        if (!accordion) return;
        const trigger = accordion.querySelector('.engraving-instructions-trigger');
        const content = accordion.querySelector('.engraving-instructions-content');
        content.style.height = '0px';
        content.style.overflow = 'hidden';
        content.style.transition = 'height 0.3s ease';
        trigger.addEventListener('click', function () {
            const isOpen = accordion.classList.contains('active');
            if (isOpen) {
                content.style.height = content.scrollHeight + 'px';
                requestAnimationFrame(function () { content.style.height = '0px'; });
                accordion.classList.remove('active');
            } else {
                content.style.height = content.scrollHeight + 'px';
                accordion.classList.add('active');
            }
        });
        content.addEventListener('transitionend', function () {
            if (accordion.classList.contains('active')) content.style.height = 'auto';
        });
    }

    function loadEngravingInstructions() {
        fetchPost('get_engraving_instructions', {}).then(result => {
            if (!result.success || !result.data) return;
            const accordion = document.querySelector('.engraving-instructions');
            if (!accordion) return;
            const title = accordion.querySelector('.engraving-instructions-trigger span');
            const content = accordion.querySelector('.engraving-instructions-content');
            if (title) title.textContent = result.data.title;
            if (content && result.data.content_html) {
                content.innerHTML = result.data.content_html;
                content.querySelectorAll('ul').forEach(list => list.classList.add('engraving-instruction-list'));
                content.querySelectorAll('ul li').forEach(item => {
                    if (!item.querySelector(':scope > span')) item.prepend(document.createElement('span'));
                });
            }
        }).catch(() => {});
    }

    function bindQuantity() {
        form.querySelector('.quantity__minus').addEventListener('click', () => {
            qtyInput.value = String(Math.max(1, (parseInt(qtyInput.value, 10) || 1) - 1));
        });
        form.querySelector('.quantity__plus').addEventListener('click', () => {
            qtyInput.value = String(Math.max(1, (parseInt(qtyInput.value, 10) || 1) + 1));
        });
    }

    function setStatus(type, html) {
        statusEl.className = 'status-banner ' + type;
        statusEl.innerHTML = html;
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        if (!selectedVariant) {
            setStatus('error', 'Select an available variant before creating the draft order.');
            return;
        }
        submitBtn.disabled = true;
        submitBtn.classList.add('btn--loading');
        const optionsParent = optionsMount.querySelector('[data-customproductoptions]');
        const payload = {
            action: 'create_custom_draft_order',
            csrf_token: boot.csrf,
            shopify_product_id: String(product.id),
            variant_id: String(selectedVariant.id),
            quantity: String(Math.max(1, parseInt(qtyInput.value, 10) || 1)),
            customer_email: document.getElementById('customerEmail').value.trim(),
            note: document.getElementById('orderNote').value.trim(),
            properties: JSON.stringify(collectProperties()),
            product_options: JSON.stringify(collectOptions(optionsParent))
        };
        fetchPost(payload.action, payload).then(result => {
            if (!result.success) {
                setStatus('error', result.message || 'Unable to create the draft order.');
                return;
            }
            const links = [];
            if (result.draft_order_admin_url) {
                links.push('<a href="' + result.draft_order_admin_url + '" target="_top">Open draft order ' + (result.draft_order_name || '') + '</a>');
            }
            if (result.custom_product_admin_url) {
                links.push('<a href="' + result.custom_product_admin_url + '" target="_top">Open custom product</a>');
            }
            setStatus('success',
                'Created <strong>' + (result.custom_product_title || 'custom product') + '</strong> at ' + money(result.price) +
                '. ' + links.join(' · ')
            );
        }).catch(() => {
            setStatus('error', 'Unable to create the draft order.');
        }).finally(() => {
            submitBtn.disabled = false;
            submitBtn.classList.remove('btn--loading');
        });
    });

    renderThumbs();
    renderVariants();
    bindQuantity();
    engravingInstructions();
    loadEngravingInstructions();
    setDisplayedPrice(selectedVariant ? selectedVariant.price : 0);

    fetchPost('get_options', { productId: String(product.id), title: product.title })
        .then(result => {
            if (!result || !result.form) {
                refreshPrice();
                return;
            }
            const wrap = document.createElement('div');
            wrap.className = 'custom-product-options-inline-wrap';
            wrap.innerHTML = result.form;
            optionsMount.appendChild(wrap);
            wrap.addEventListener('change', refreshPrice);
            initCustomSelects(wrap);
            initConditionalLogic(wrap);
            const optionsParent = wrap.querySelector('[data-customproductoptions]');
            if (optionsParent) applyInventoryAvailability(optionsParent).then(refreshPrice);
            else refreshPrice();
        })
        .catch(() => refreshPrice());
})();
