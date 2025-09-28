<?php
// SelectRole/menu_detail.php — Standalone + Popup fragment (PSU tone, decorated) — no image version
declare(strict_types=1);
session_start();
if (empty($_SESSION['uid'])) { header("Location: ../index.php"); exit; }
require __DIR__ . '/../db.php';
$conn->set_charset('utf8mb4');

$menu_id = (int)($_GET['id'] ?? 0);
if ($menu_id <= 0) { header("Location: front_store.php"); exit; }

$stmt = $conn->prepare("SELECT m.menu_id, m.name, m.price, m.image, c.category_name
                        FROM menu m LEFT JOIN categories c ON m.category_id=c.category_id
                        WHERE m.menu_id=?");
$stmt->bind_param("i", $menu_id);
$stmt->execute();
$menu = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$menu) { header("Location: front_store.php"); exit; }

function money_fmt($n){ return number_format((float)$n, 2); }

/* ----- โหมดแก้ไข (เติมค่าเดิม) ----- */
$editMode   = (int)($_GET['edit'] ?? 0) === 1;
$old_key    = isset($_GET['key']) ? (string)$_GET['key'] : '';
$currentQty = 1; $currentNote = '';

if ($editMode && $old_key !== '' && isset($_SESSION['cart'][$old_key])) {
  $currentQty  = max(1, (int)($_SESSION['cart'][$old_key]['qty'] ?? 1));
  $currentNote = (string)($_SESSION['cart'][$old_key]['note'] ?? '');
}

/* ----- แตก note เก่า (ถ้ามี) เพื่อ preselect ----- */
$selSize='ธรรมดา'; $selSweet='ปกติ'; $selIce='ปกติ'; $selToppings=[]; $selFree='';
if ($currentNote !== '') {
  $parts = explode(' | ', $currentNote);
  foreach ($parts as $p) {
    $p = trim($p);
    if (stripos($p,'ขนาด:')===0)         $selSize  = trim(mb_substr($p, mb_strlen('ขนาด:')));
    elseif (stripos($p,'หวาน:')===0)     $selSweet = trim(mb_substr($p, mb_strlen('หวาน:')));
    elseif (stripos($p,'น้ำแข็ง:')===0) $selIce   = trim(mb_substr($p, mb_strlen('น้ำแข็ง:')));
    elseif (stripos($p,'ท็อปปิง:')===0) {
      $tp = trim(mb_substr($p, mb_strlen('ท็อปปิง:'))); if ($tp!=='') $selToppings = array_map('trim', explode(',', $tp));
    } elseif (stripos($p,'หมายเหตุ:')===0) $selFree = trim(mb_substr($p, mb_strlen('หมายเหตุ:')));
  }
}

/* ----- ดึงท็อปปิงจากตาราง toppings (ทุกเมนูใช้ชุดเดียวกัน) ----- */
$toppings = [];
$rs = $conn->query("SELECT topping_id, name, base_price FROM toppings WHERE is_active=1 ORDER BY name");
while($row = $rs->fetch_assoc()){
  $toppings[] = $row; // [topping_id, name, base_price]
}
$isPopup = (int)($_GET['popup'] ?? 0) === 1;

/* ============ VIEW ============ */
if (!$isPopup):
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>เลือกตัวเลือก • <?= htmlspecialchars($menu['name'],ENT_QUOTES,'UTF-8') ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet"
 href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<link rel="stylesheet"
 href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<style>
/* พื้นหลังหน้าแบบไลต์: ยังใช้โทน PSU */
body{ background:linear-gradient(135deg,#0D4071,#4173BD); color:#fff; font-family:"Segoe UI",Tahoma,Arial,sans-serif; }
.containerx{ max-width:900px; margin:24px auto; padding:0 12px; }

/* ===== THEME TOKENS (fallback + Dark ที่คุณกำหนด) ===== */
:root{
  --text-strong:#0D4071;
  --text-normal:#244b77;
  --text-muted:#5f7a99;

  --bg-grad1:#0a1a30;
  --bg-grad2:#0D4071;

  --surface:#0f223a;
  --surface-2:#0a1f36;
  --surface-3:#122a47;
  --ink:#eaf3ff;
  --ink-muted:#b2c5e3;

  --brand-900:#dff3ff;
  --brand-700:#9cc4dd;
  --brand-500:#00ADB5;
  --brand-400:#27C8CF;
  --brand-300:#73E2E6;

  --aqua-500:#00ADB5;
  --aqua-400:#5ED8DD;
  --mint-300:#223037;
  --violet-200:#5C6A74;

  --shadow-lg:0 22px 66px rgba(0,0,0,.55);
  --shadow:   0 14px 32px rgba(0,0,0,.42);
}

/* ใช้ชุดที่คุณให้ */
:root[data-theme="dark"]{
  /* Teal-Graphite Dark */
  --text-strong:#F4F7F8;
  --text-normal:#E6EBEE;
  --text-muted:#B9C2C9;

  --bg-grad1:#222831;    /* graphite deep */
  --bg-grad2:#393E46;    /* graphite */

  --surface:#1C2228;     /* not pure black */
  --surface-2:#232A31;
  --surface-3:#2B323A;
  --ink:#F4F7F8;
  --ink-muted:#CFEAED;

  --brand-900:#EEEEEE;   /* for badges on dark */
  --brand-700:#BFC6CC;
  --brand-500:#00ADB5;   /* accent unchanged */
  --brand-400:#27C8CF;
  --brand-300:#73E2E6;

  --aqua-500:#00ADB5;
  --aqua-400:#5ED8DD;
  --mint-300:#223037;
  --violet-200:#5C6A74;

  --shadow-lg:0 22px 66px rgba(0,0,0,.55);
  --shadow:   0 14px 32px rgba(0,0,0,.42);
}
</style>
</head>
<body>
<div class="containerx">
<?php endif; ?>

<!-- ===== Popup / Inner Content (NO IMAGE) ===== -->
<link rel="stylesheet"
 href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<style>
/* ===== Popup ใช้ตัวแปรธีมทั้งหมด ===== */
#popup-root{
  background: linear-gradient(180deg, color-mix(in oklab, var(--surface), black 6%), var(--surface));
  color: var(--ink);
  border-radius:14px;
  border:1px solid color-mix(in oklab, var(--violet-200), black 18%);
  box-shadow: var(--shadow-lg);
  max-width:900px; margin:0 auto; overflow:hidden;
}
#popup-head{
  background:
    radial-gradient(240px 90px at 105% -20%, color-mix(in oklab, var(--aqua-500), transparent 75%), transparent),
    linear-gradient(135deg, color-mix(in oklab, var(--surface-2), white 8%), var(--surface-3));
  padding:12px 14px;
  border-bottom:1px solid color-mix(in oklab, var(--brand-700), black 30%);
}
.menu-title{ font-weight:900; margin:0; color:var(--text-strong); display:flex; align-items:center; gap:8px; font-size:1.05rem; }
.badge-cat{
  display:inline-flex; align-items:center; gap:6px;
  background: color-mix(in oklab, var(--surface-2), white 6%);
  color: var(--ink);
  border:1px solid color-mix(in oklab, var(--brand-700), black 20%);
  border-radius:999px; padding:3px 10px; font-weight:800; font-size:.8rem;
}
.price-tag{
  font-size:1.25rem; font-weight:900; color:#062534;
  background: linear-gradient(180deg, var(--brand-400), var(--brand-500));
  display:inline-flex; align-items:center; gap:8px; padding:6px 14px; border-radius:12px;
  box-shadow:0 10px 22px color-mix(in oklab, var(--brand-500), transparent 65%), inset 0 -2px 0 rgba(0,0,0,.15);
}
.price-tag i{opacity:.9}

#popup-body{ padding:12px 14px; }
#popup-body .hint{ color:var(--ink-muted); font-weight:700 }

/* sections & fields */
.box{
  background: var(--surface);
  border:1px solid color-mix(in oklab, var(--brand-700), black 28%);
  border-radius:12px; padding:10px;
}
.box + .box{ margin-top:4px; }
#popup-root .sec-title{
  margin:10px 0 6px; font-weight:900; color:var(--text-normal);
  letter-spacing:.2px; display:flex; align-items:center; gap:8px; font-size:.95rem
}

/* chips */
#popup-root input[type="radio"], #popup-root input[type="checkbox"]{ appearance:none; display:none; }
#popup-root .chip{
  display:inline-flex; align-items:center; gap:8px; padding:7px 12px; margin:4px 8px 4px 0;
  border-radius:999px; font-weight:800; color:var(--text-normal);
  background: color-mix(in oklab, var(--surface-2), white 4%);
  border:1px solid color-mix(in oklab, var(--brand-700), black 20%);
  cursor:pointer; user-select:none; transition:.15s; font-size:.9rem
}
#popup-root .chip:hover{ transform:translateY(-1px); }
#popup-root input:checked + .chip{
  background: linear-gradient(180deg, var(--brand-400), var(--brand-500));
  color:#062534; border-color: color-mix(in oklab, var(--brand-500), black 18%);
  box-shadow:0 8px 18px color-mix(in oklab, var(--brand-500), transparent 72%);
}
.chip .price{ font-size:.8rem; font-weight:900; opacity:.95 }
.option-grid{ display:flex; flex-wrap:wrap; gap:8px 10px; }

/* inputs */
#popup-root textarea.form-control, #popup-root input.form-control{
  background: var(--surface-2);
  color: var(--ink);
  border:2px solid color-mix(in oklab, var(--brand-700), black 25%);
  border-radius:10px;
}
#popup-root textarea.form-control::placeholder{ color: color-mix(in oklab, var(--ink), black 35%); }
#popup-root textarea.form-control:focus, #popup-root input.form-control:focus{
  border-color: var(--brand-400);
  box-shadow:0 0 0 .18rem color-mix(in oklab, var(--brand-400), white 65%);
}

/* qty */
.qty-wrap{ display:flex; align-items:center; gap:8px; max-width:220px }
.qty-wrap .btn-step{
  width:38px; height:38px; display:inline-flex; align-items:center; justify-content:center;
  background: var(--surface-2);
  border:2px solid color-mix(in oklab, var(--brand-700), black 25%);
  border-radius:10px; font-weight:900; color:var(--text-normal);
}
.qty-wrap .btn-step:active{ transform:translateY(1px) }

/* footer */
#popup-root .footer-actions{
  border-top:1px dashed color-mix(in oklab, var(--brand-700), black 25%);
  margin:12px -14px 0; padding:12px 14px; display:flex; gap:10px;
}
#popup-root .btn-cancel{
  background: var(--surface-2); color: var(--text-strong);
  border:2px solid color-mix(in oklab, var(--violet-200), black 18%);
  font-weight:800; border-radius:10px; padding:.5rem .9rem;
}
#popup-root .btn-cancel:hover{ filter:brightness(1.06); }
/* ปุ่มหลัก – โทนอ่านง่ายตามรีเควส */
#popup-root .btn-save{
  flex:1;
  background: linear-gradient(180deg, #3aa3ff, #1f7ee8); /* ฟ้าอ่านง่าย */
  color:#fff; border:0; font-weight:900; letter-spacing:.2px; border-radius:12px; padding:.55rem .9rem;
  box-shadow:0 10px 22px rgba(31,126,232,.35), inset 0 -2px 0 rgba(0,0,0,.15);
}
#popup-root .btn-save:hover{ filter:brightness(1.05); }

/* hint / subnote */
#calc_total_hint{ font-weight:900; color:var(--ink-muted); font-size:.9rem }
.subnote{ font-size:.8rem; color:var(--text-muted); }

/* grid */
#form-grid{ display:grid; grid-template-columns:1fr; gap:10px; }
.span-2{ grid-column: 1 / -1; }
@media (min-width:768px){
  #form-grid{ grid-template-columns: 1fr 1fr; gap:12px; }
}

/* focus a11y */
#popup-root .btn-cancel:focus-visible,
#popup-root .btn-save:focus-visible,
#popup-root .chip:focus-visible{
  outline:3px solid color-mix(in oklab, var(--brand-500), white 35%);
  outline-offset:2px; border-radius:12px;
}
</style>

<div id="popup-root" role="dialog" aria-label="ตัวเลือกเมนู">
  <div id="popup-head">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h4 class="menu-title mb-1"><i class="bi bi-cup-hot"></i> <?= htmlspecialchars($menu['name'],ENT_QUOTES,'UTF-8') ?></h4>
        <span class="badge-cat"><i class="bi bi-tag"></i> <?= htmlspecialchars($menu['category_name'] ?? 'เมนู',ENT_QUOTES,'UTF-8') ?></span>
      </div>
      <div class="price-tag" title="ราคาเมนูพื้นฐาน">
        <i class="bi bi-cash-coin"></i> <?= money_fmt($menu['price']) ?> ฿
      </div>
    </div>
  </div>

  <div id="popup-body">
    <form method="post" action="front_store.php" id="menuForm">
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="menu_id" value="<?= (int)$menu['menu_id'] ?>">
      <?php if ($editMode): ?>
        <input type="hidden" name="edit" value="1">
        <input type="hidden" name="old_key" value="<?= htmlspecialchars($old_key,ENT_QUOTES,'UTF-8') ?>">
      <?php endif; ?>

      <!-- ค่าบวกเพิ่มจากท็อปปิง -->
      <input type="hidden" name="addon_total" id="addon_total" value="0">

      <div id="form-grid">
        <div class="box">
          <div class="sec-title"><i class="bi bi-emoji-smile"></i> ระดับความหวาน</div>
          <?php $opts=['หวานน้อย','ปกติ','หวานมาก']; foreach($opts as $i=>$sv): $id='sweet'.($i+1); $checked=($selSweet===$sv)?'checked':''; ?>
            <input type="radio" name="sweet" id="<?= $id ?>" value="<?= htmlspecialchars($sv,ENT_QUOTES,'UTF-8') ?>" <?= $checked ?>>
            <label class="chip" for="<?= $id ?>"><i class="bi bi-droplet-half"></i> <?= htmlspecialchars($sv,ENT_QUOTES,'UTF-8') ?></label>
          <?php endforeach; ?>
        </div>

        <div class="box">
          <div class="sec-title"><i class="bi bi-snow"></i> น้ำแข็ง</div>
          <?php $opts=['ไม่ใส่น้ำแข็ง','ปกติ','เยอะ']; foreach($opts as $i=>$iv): $id='ice'.($i+1); $checked=($selIce===$iv)?'checked':''; ?>
            <input type="radio" name="ice" id="<?= $id ?>" value="<?= htmlspecialchars($iv,ENT_QUOTES,'UTF-8') ?>" <?= $checked ?>>
            <label class="chip" for="<?= $id ?>"><i class="bi bi-asterisk"></i> <?= htmlspecialchars($iv,ENT_QUOTES,'UTF-8') ?></label>
          <?php endforeach; ?>
        </div>

        <div class="box">
          <div class="sec-title"><i class="bi bi-aspect-ratio"></i> ขนาด</div>
          <?php $opts=['เล็ก','ธรรมดา','ใหญ่']; foreach($opts as $i=>$sv): $id='size'.($i+1); $checked=($selSize===$sv)?'checked':''; ?>
            <input type="radio" name="size" id="<?= $id ?>" value="<?= htmlspecialchars($sv,ENT_QUOTES,'UTF-8') ?>" <?= $checked ?>>
            <label class="chip" for="<?= $id ?>"><i class="bi bi-arrows-fullscreen"></i> <?= htmlspecialchars($sv,ENT_QUOTES,'UTF-8') ?></label>
          <?php endforeach; ?>
        </div>

        <div class="box span-2">
          <div class="sec-title"><i class="bi bi-bag-plus"></i> ท็อปปิง (เลือกได้หลายอย่าง)</div>
          <div class="option-grid">
            <?php foreach($toppings as $i=>$tp):
              $id='tp'.($i+1);
              $checked=in_array($tp['name'],$selToppings,true)?'checked':'';
              $p = (float)$tp['base_price'];
            ?>
              <input
                type="checkbox"
                name="toppings[]"
                id="<?= $id ?>"
                value="<?= (int)$tp['topping_id'] ?>"
                data-title="<?= htmlspecialchars($tp['name'],ENT_QUOTES,'UTF-8') ?>"
                data-price="<?= htmlspecialchars(number_format($p,2,'.',''),ENT_QUOTES,'UTF-8') ?>"
                <?= $checked ?>
              >
              <label class="chip" for="<?= $id ?>">
                <i class="bi bi-plus-circle"></i>
                <span><?= htmlspecialchars($tp['name'],ENT_QUOTES,'UTF-8') ?></span>
                <?php if($p>0): ?><span class="price">+<?= money_fmt($p) ?> ฿</span><?php endif; ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="box span-2">
          <div class="sec-title"><i class="bi bi-pencil-square"></i> หมายเหตุเพิ่มเติม</div>
          <textarea class="form-control" name="note_free" rows="2" placeholder="เช่น ไม่ใส่ฝา งดหลอด ฯลฯ"><?= htmlspecialchars($selFree,ENT_QUOTES,'UTF-8') ?></textarea>
        </div>

        <div class="box">
          <div class="sec-title justify-content-between">
            <span><i class="bi bi-123"></i> จำนวน</span>
            <small id="calc_total_hint" class="hint"></small>
          </div>
          <div class="qty-wrap" role="group" aria-label="ปรับจำนวน">
            <button class="btn-step" type="button" id="qminus" aria-label="ลดจำนวน"><i class="bi bi-dash-lg"></i></button>
            <input type="number" class="form-control" name="qty" id="qty" value="<?= (int)$currentQty ?>" min="1" style="max-width:110px">
            <button class="btn-step" type="button" id="qplus" aria-label="เพิ่มจำนวน"><i class="bi bi-plus-lg"></i></button>
          </div>
          <div class="subnote mt-2"><i class="bi bi-info-circle"></i> ราคาสุทธิจริงจะคำนวณโปรโมชัน (ถ้ามี) ตอนเพิ่มลงออเดอร์</div>
        </div>
      </div>

      <input type="hidden" name="note" id="note">
      <div class="footer-actions">
        <?php if(!$isPopup): ?>
          <a href="front_store.php" class="btn btn-cancel"><i class="bi bi-x-circle"></i> ยกเลิก</a>
        <?php else: ?>
          <button type="button" class="btn btn-cancel" onclick="window.parent?.document.getElementById('menuModalClose')?.click()"><i class="bi bi-x-circle"></i> ยกเลิก</button>
        <?php endif; ?>
        <button type="submit" class="btn btn-save">
          <i class="bi <?= $editMode ? 'bi-check2-square' : 'bi-bag-plus' ?>"></i>
          <?= $editMode ? 'บันทึกการแก้ไข' : 'เพิ่มในออเดอร์' ?>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
(function(){
  const root = document;
  const basePrice = <?= json_encode((float)$menu['price']) ?>;
  const money = n => Number(n).toFixed(2);

  function collectNoteAndAddon(){
    const pick = name => (root.querySelector(`input[name="${name}"]:checked`)||{}).value || '';
    const parts = [];
    const size = pick('size'), sweet = pick('sweet'), ice = pick('ice');
    if(size)  parts.push('ขนาด: '+size);
    if(sweet) parts.push('หวาน: '+sweet);
    if(ice)   parts.push('น้ำแข็ง: '+ice);
    const tops = Array.from(root.querySelectorAll('input[name="toppings[]"]:checked'));
    const free = (root.querySelector('textarea[name="note_free"]')?.value || '').trim();
    if(tops.length) parts.push('ท็อปปิง: '+tops.map(x=>x.dataset.title || x.value).join(', '));
    if(free) parts.push('หมายเหตุ: '+free);

    // รวมราคา topping
    const addon = tops.reduce((s, el)=> s + parseFloat(el.dataset.price||'0'), 0);
    (root.getElementById('note')||{}).value = parts.join(' | ');
    (root.getElementById('addon_total')||{}).value = money(addon);

    // hint แสดงยอดต่อแก้ว/รวม
    const qty = Math.max(1, parseInt((root.getElementById('qty')||{}).value||'1', 10));
    const perCup = basePrice + addon;
    const hint = root.getElementById('calc_total_hint');
    if(hint){ hint.textContent = `ต่อแก้ว: ${money(perCup)} ฿  •  รวม ~ ${money(perCup*qty)} ฿`; }
  }

  // ปุ่ม +/- และอัปเดต
  const qMinus = document.getElementById('qminus');
  const qPlus  = document.getElementById('qplus');
  const qtyInp = document.getElementById('qty');
  qMinus?.addEventListener('click', ()=>{ const v=Math.max(1,(parseInt(qtyInp.value||'1',10)-1)); qtyInp.value=v; collectNoteAndAddon(); });
  qPlus?.addEventListener('click',  ()=>{ const v=Math.max(1,(parseInt(qtyInp.value||'1',10)+1)); qtyInp.value=v; collectNoteAndAddon(); });

  root.querySelectorAll('input[name="toppings[]"]').forEach(el=> el.addEventListener('change', collectNoteAndAddon));
  qtyInp?.addEventListener('input', collectNoteAndAddon);
  root.getElementById('menuForm')?.addEventListener('submit', collectNoteAndAddon);

  // init
  collectNoteAndAddon();

  // ถ้าหน้านี้ถูกเปิดจาก parent ให้ดึงค่า data-theme มาใช้ เพื่อโทนตรงกับหน้าแม่
  try{
    const t = window.parent?.document?.documentElement?.getAttribute('data-theme');
    if(t){ document.documentElement.setAttribute('data-theme', t); }
  }catch(_){}
})();
</script>

<?php if (!$isPopup): ?>
</div>
</body>
</html>
<?php endif; ?>
