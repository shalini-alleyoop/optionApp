(function () {
  'use strict';
  const endpoint = 'https://apps.royalhawaiianheritage.com/validate-option-inventory.php';
  let validating = false;

  function isCheckoutControl(target) {
    return target && target.closest && target.closest('button[name="checkout"], input[name="checkout"], a[href*="/checkout"]');
  }
  function errorBox() {
    let box = document.querySelector('[data-option-inventory-errors]');
    if (!box) {
      box = document.createElement('div'); box.dataset.optionInventoryErrors=''; box.setAttribute('role','alert');
      box.style.cssText='display:none;margin:12px 0;padding:12px;border:1px solid #c33;background:#fff2f2;color:#8b0000;font-size:14px;';
      const host=document.querySelector('[data-cart-checkout-buttons]')||document.querySelector('[data-checkout-buttons]')||document.body;
      host.parentNode.insertBefore(box,host);
    }
    return box;
  }
  function showErrors(errors) {
    const box=errorBox(); box.innerHTML='<strong>Please update your options before checkout:</strong><ul style="margin:8px 0 0 18px">'+errors.map(e=>'<li>'+String(e.message||'An option is unavailable.').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))+'</li>').join('')+'</ul>';box.style.display='block';box.scrollIntoView({behavior:'smooth',block:'center'});
  }
  function clearErrors() { document.querySelectorAll('[data-option-inventory-errors]').forEach(box => { box.style.display='none'; box.innerHTML=''; }); }
  function setBusy(busy) { document.querySelectorAll('button[name="checkout"],input[name="checkout"]').forEach(btn=>{btn.disabled=busy;btn.setAttribute('aria-busy',busy?'true':'false');}); }
  async function validateAndCheckout() {
    if(validating)return;validating=true;clearErrors();setBusy(true);
    try {
      const cartResponse=await fetch((window.Shopify&&Shopify.routes&&Shopify.routes.root||'/')+'cart.js',{credentials:'same-origin',headers:{Accept:'application/json'}});
      if(!cartResponse.ok)throw new Error('Unable to read the cart.');
      const cart=await cartResponse.json();
      const lines=(cart.items||[]).map(item=>({key:item.key,variant_id:item.variant_id,quantity:item.quantity,option_value_ids:(item.properties||{})['_Option Value IDs']||''}));
      if(!lines.some(line=>String(line.option_value_ids).trim())){window.location.assign('/checkout');return;}
      const response=await fetch(endpoint,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({shop:window.Shopify&&Shopify.shop||'',cart_token:cart.token,lines})});
      const result=await response.json().catch(()=>({success:false,checkout_allowed:false,errors:[{message:'Inventory validation returned an invalid response.'}]}));
      if(response.ok&&result.success&&result.checkout_allowed){window.location.assign('/checkout');return;}
      showErrors(result.errors&&result.errors.length?result.errors:[{message:'We could not verify option availability. Please try again.'}]);
    } catch(error){showErrors([{message:'We could not verify option availability. Please try again.'}]);}
    finally{validating=false;setBusy(false);}
  }
  document.addEventListener('click',function(event){const control=isCheckoutControl(event.target);if(!control)return;event.preventDefault();event.stopImmediatePropagation();validateAndCheckout();},true);
  document.addEventListener('submit',function(event){const submitter=event.submitter;if(submitter&&submitter.name==='checkout'){event.preventDefault();event.stopImmediatePropagation();validateAndCheckout();}},true);
  document.addEventListener('theme:cart-drawer:open',clearErrors);
  document.addEventListener('theme:cart-drawer:close',clearErrors);
  document.addEventListener('theme:cart:change',clearErrors);
  document.addEventListener('DOMContentLoaded',function(){document.querySelectorAll('.additional-checkout-buttons').forEach(el=>{el.style.display='none';el.setAttribute('aria-hidden','true');});});
})();
