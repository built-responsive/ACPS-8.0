<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include('shopping_cart.class.php');
$Cart = new Shopping_Cart('shopping_cart');

$thisPhoto = $_GET['p'] ?? "0001";
$photoID = basename($thisPhoto, ".jpg");

$fourby=$fiveby=$eightby=$email=0;
$isEdit = false;
foreach ($Cart->getItems() as $code=>$q){
	list($prod,$pid)=explode('-',$code);
	if($pid==$photoID){
		if($prod=='4x6')$fourby=$q;
		if($prod=='5x7')$fiveby=$q;
		if($prod=='8x10')$eightby=$q;
		if($prod=='EML')$email=$q;
		if($q > 0) $isEdit = true;
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add to Cart</title>

<script src="/public/assets/js/jquery-3.2.1.min.js"></script>
<script>
//==========================//
//   PRICE CALCULATION LOGIC
//==========================//
function changeQty(id, delta){
  const el = document.getElementById(id);
  let v = parseInt(el.value || 0) + delta;
  if (v < 0) v = 0;
  el.value = v;
  updateSubtotal();
}

function updateSubtotal(){
  let total = 0;
  const qty4 = parseInt(document.getElementById('4x6').value || 0);
  const qty5 = parseInt(document.getElementById('5x7').value || 0);
  const qty8 = parseInt(document.getElementById('8x10').value || 0);
  const eml = document.getElementById('EML').checked ? 1 : 0;

  const price4 = 8.00;
  const price5 = 12.00;
  const price8 = 20.00;

  // 4x6: $8 each or 5 for $25
  let t4 = 0;
  if (qty4 > 0) {
    const bundle4 = Math.floor(qty4 / 5);
    const remain4 = qty4 % 5;
    t4 = (bundle4 * 25) + (remain4 * price4);
  }

  // 5x7: $12 each or 3 for $30
  let t5 = 0;
  if (qty5 > 0) {
    const bundle5 = Math.floor(qty5 / 3);
    const remain5 = qty5 % 3;
    t5 = (bundle5 * 30) + (remain5 * price5);
  }

  let t8 = qty8 * price8;

  let emlPrice = 15.00;
  if (eml && (qty4 > 0 || qty5 > 0 || qty8 > 0)) emlPrice = 3.00;
  const tE = eml ? emlPrice : 0;

  total = t4 + t5 + t8 + tE;

  document.getElementById('p4x6').textContent = qty4 > 0 ? '$' + t4.toFixed(2) : '';
  document.getElementById('p5x7').textContent = qty5 > 0 ? '$' + t5.toFixed(2) : '';
  document.getElementById('p8x10').textContent = qty8 > 0 ? '$' + t8.toFixed(2) : '';
  document.getElementById('pEML').textContent  = eml ? '$' + tE.toFixed(2) : '';
  document.getElementById('subtotal').textContent = 'Subtotal: $' + total.toFixed(2);
}

document.addEventListener('DOMContentLoaded', ()=>{
  document.querySelectorAll('input').forEach(el=>{
    el.addEventListener('change', updateSubtotal);
    el.addEventListener('input', updateSubtotal);
  });
  updateSubtotal();
});
</script>
<link rel="stylesheet" type="text/css" href="/public/assets/css/acps.css">
</head>

<body class="cart-modal-body">
<div id="mainWrap" class="cart-modal-wrap">
  
  <div class="left-panel cart-modal-panel-left">
 <div class="promo-box cart-promo-box">
      <div class="promo-title cart-promo-title">
        Discounts <span class="note-text cart-note-text">(Note: volume discounts are bundle-based and applied automatically)</span>
      </div>
      <ul class="promo-list cart-promo-list">
        <li>4×6: $8 each — or 5 for $25</li>
        <li>5×7: $12 each — or 3 for $30</li>
        <li>Digital Image: $15.00 ea. / 5 or more $7 ea.</li>
        <li>Digital Image of same printed photo only $3</li>
      </ul>
    </div>

    <form name="frmCart" action="cart.php" target="cart" method="post">
      <input type="hidden" name="photoID" value="<?php echo $photoID; ?>">

      <div class="form-group cart-form-group">
        <label for="EML">Single high res digital image</label>
        <input type="checkbox" name="EML" id="EML" value="1" <?php if($email>0)echo'checked';?> onchange="updateSubtotal()">
        <span id="pEML" class="price-tag cart-price-tag"></span>
      </div>

      <div class="form-group cart-form-group">
        <label for="4x6">4×6 Prints</label>
        <div class="qty-buttons cart-qty-buttons">
          <button type="button" class="qty-button cart-qty-button" onclick="changeQty('4x6',-1)">−</button>
          <input type="text" name="4x6" id="4x6" value="<?php echo $fourby;?>" maxlength="3" inputmode="numeric" pattern="[0-9]*" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
          <button type="button" class="qty-button cart-qty-button" onclick="changeQty('4x6',1)">+</button>
        </div>
        <span id="p4x6" class="price-tag cart-price-tag"></span>
      </div>

      <div class="form-group cart-form-group">
        <label for="5x7">5×7 Prints</label>
        <div class="qty-buttons cart-qty-buttons">
          <button type="button" class="qty-button cart-qty-button" onclick="changeQty('5x7',-1)">−</button>
          <input type="text" name="5x7" id="5x7" value="<?php echo $fiveby;?>" maxlength="3" inputmode="numeric" pattern="[0-9]*" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
          <button type="button" class="qty-button cart-qty-button" onclick="changeQty('5x7',1)">+</button>
        </div>
        <span id="p5x7" class="price-tag cart-price-tag"></span>
      </div>

      <div class="form-group cart-form-group">
        <label for="8x10">8×10 Prints</label>
        <div class="qty-buttons cart-qty-buttons">
          <button type="button" class="qty-button cart-qty-button" onclick="changeQty('8x10',-1)">−</button>
          <input type="text" name="8x10" id="8x10" value="<?php echo $eightby;?>" maxlength="3" inputmode="numeric" pattern="[0-9]*" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
          <button type="button" class="qty-button cart-qty-button" onclick="changeQty('8x10',1)">+</button>
        </div>
        <span id="p8x10" class="price-tag cart-price-tag"></span>
      </div>

      <div id="subtotal" class="subtotal cart-subtotal" style="text-align:center;">Subtotal: $0.00</div>

      <div style="text-align:center;margin-top:1.2rem;">
        <button type="submit" class="btn cart-btn" id="submitBtn"><?php echo $isEdit ? 'UPDATE CART' : 'ADD TO CART'; ?></button>
        <button type="button" class="btn cart-btn" onclick="window.top.closeCartModal(); return false;">CANCEL</button>
      </div>
    </form>
  </div>

  <div class="right-panel cart-modal-panel-right">
    <img src="<?php echo htmlspecialchars($thisPhoto); ?>" alt="Preview Photo">
  </div>
</div>

<script>updateSubtotal();</script>
<script>
// Prevent duplicate submissions and close modal after form submission
(function() {
  let formSubmitted = false;
  const form = document.forms['frmCart'];
  const submitBtn = document.getElementById('submitBtn');
  
  form.addEventListener('submit', function(e) {
    if (formSubmitted) {
      e.preventDefault();
      return false;
    }
    
    formSubmitted = true;
    submitBtn.disabled = true;
    submitBtn.textContent = 'PROCESSING...';
    
    // Close modal after short delay to allow form to submit to cart iframe
    setTimeout(function() {
      if (window.top && window.top.closeCartModal) {
        window.top.closeCartModal();
      }
    }, 400);
  });
})();
</script>
</body>
</html>
