/**
 * FoodMitra hotel-admin — new delivery order popup (EatnSay-style).
 * Polls ha-api/check-new-orders.php every 5s; Accept / Cancel + looping alert.
 */
(function () {
  if (window.__FmOrderNotifyInitialized) return;
  window.__FmOrderNotifyInitialized = true;

  var POLL_MS = 5000;
  var processingQueue = [];
  var currentNotification = null;
  var pollInFlight = false;
  var lastQueueSignature = '';
  var pollWorker = null;
  var pollTimer = null;
  var isPlaying = false;
  var audioCtx = null;
  var beepNodes = null;
  var audioEl = null;

  try {
    audioEl = new Audio('assets/alert-sound.wav');
    audioEl.loop = true;
    audioEl.volume = 1;
    audioEl.preload = 'auto';
  } catch (e) {
    audioEl = null;
  }

  try {
    var workerSrc =
      'var t=null;onmessage=function(e){var d=e.data||{};' +
      'if(d.cmd==="start"){if(t)clearInterval(t);t=setInterval(function(){postMessage("tick");},d.interval||5000);}' +
      'else if(d.cmd==="stop"&&t){clearInterval(t);t=null;}};';
    pollWorker = new Worker(URL.createObjectURL(new Blob([workerSrc], { type: 'application/javascript' })));
    pollWorker.onmessage = function () {
      checkForNewOrders();
    };
  } catch (e) {
    pollWorker = null;
  }

  document.addEventListener(
    'click',
    function () {
      unlockAudio();
    },
    { once: true }
  );

  function unlockAudio() {
    try {
      if (audioEl) {
        audioEl.play().then(function () {
          audioEl.pause();
          audioEl.currentTime = 0;
        }).catch(function () {});
      }
      ensureAudioCtx();
    } catch (e) {}
  }

  function ensureAudioCtx() {
    if (audioCtx) return audioCtx;
    var AC = window.AudioContext || window.webkitAudioContext;
    if (!AC) return null;
    audioCtx = new AC();
    return audioCtx;
  }

  function startBeepLoop() {
    var ctx = ensureAudioCtx();
    if (!ctx) return;
    if (ctx.state === 'suspended') ctx.resume();
    stopBeepLoop();
    var osc = ctx.createOscillator();
    var gain = ctx.createGain();
    osc.type = 'square';
    osc.frequency.value = 880;
    gain.gain.value = 0.08;
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.start();
    // Pulse volume
    var pulse = setInterval(function () {
      if (!beepNodes) return;
      var g = beepNodes.gain.gain;
      var now = ctx.currentTime;
      g.cancelScheduledValues(now);
      g.setValueAtTime(0.02, now);
      g.linearRampToValueAtTime(0.12, now + 0.12);
      g.linearRampToValueAtTime(0.02, now + 0.35);
    }, 450);
    beepNodes = { osc: osc, gain: gain, pulse: pulse };
  }

  function stopBeepLoop() {
    if (!beepNodes) return;
    try {
      clearInterval(beepNodes.pulse);
      beepNodes.osc.stop();
      beepNodes.osc.disconnect();
      beepNodes.gain.disconnect();
    } catch (e) {}
    beepNodes = null;
  }

  function playSound() {
    isPlaying = true;
    if (audioEl) {
      audioEl.currentTime = 0;
      audioEl.loop = true;
      audioEl.play().then(function () {
        /* ok */
      }).catch(function () {
        startBeepLoop();
      });
    } else {
      startBeepLoop();
    }
  }

  function stopSound() {
    isPlaying = false;
    if (audioEl) {
      try {
        audioEl.pause();
        audioEl.currentTime = 0;
      } catch (e) {}
    }
    stopBeepLoop();
  }

  function getNotifId(n) {
    if (!n) return '';
    return String(n.order_id || n.order_number || '');
  }

  function buildQueueSignature(queue) {
    return queue
      .map(function (n) {
        return getNotifId(n);
      })
      .join('|');
  }

  function invalidateOrderPollCache() {
    lastQueueSignature = '';
    currentNotification = null;
    checkForNewOrders();
  }

  function scheduleOrderPoll(immediate) {
    if (pollWorker) {
      pollWorker.postMessage({ cmd: 'start', interval: POLL_MS });
      if (immediate) checkForNewOrders();
      return;
    }
    if (pollTimer) clearTimeout(pollTimer);
    if (immediate) checkForNewOrders();
    pollTimer = setTimeout(function () {
      pollTimer = null;
      checkForNewOrders();
    }, POLL_MS);
  }

  function setDeliveryFooterVisible(show) {
    var d = document.getElementById('notifyDeliveryFooter');
    var a = document.getElementById('actionButton');
    if (d) d.classList.toggle('hidden', !show);
    if (d) d.classList.toggle('flex', show);
    if (a) a.classList.toggle('hidden', show);
  }

  function init() {
    if (window.FoodMitra_ORDER_NOTIFY === false) return;
    if ('Notification' in window && Notification.permission !== 'granted') {
      try {
        Notification.requestPermission();
      } catch (e) {}
    }

    var modalHtml =
      '<div id="orderNotificationModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-[9999] hidden items-center justify-center p-4">' +
      '<div class="bg-white rounded-xl shadow-2xl w-full max-w-sm overflow-hidden text-center">' +
      '<div class="bg-primary p-4 text-white border-b border-primary-hover">' +
      '<div class="mb-3 pb-3 border-b border-white/20">' +
      '<h2 class="font-bold text-sm text-center tracking-tight" id="notifyHotelName">FoodMitra</h2>' +
      '</div>' +
      '<div class="flex justify-between items-center">' +
      '<div class="flex-1">' +
      '<p class="font-medium text-xs uppercase tracking-wider mb-0.5 opacity-90" id="notifyHeaderTitle">Delivery Order</p>' +
      '<h3 class="font-bold text-lg leading-none" id="notifyOrderId">#ID</h3>' +
      '</div>' +
      '<div class="flex-1">' +
      '<p class="text-xs opacity-90" id="notifyDiningType">Customer</p>' +
      '<p class="font-bold text-lg leading-none truncate px-1" id="notifyDiningNumber">--</p>' +
      '</div>' +
      '</div></div>' +
      '<div class="p-5 space-y-4 max-h-[60vh] overflow-y-auto bg-gray-50/50 text-left">' +
      '<div class="space-y-3" id="notifyItemsList"></div>' +
      '<div id="notifyInstructionsContainer" class="hidden">' +
      '<div class="bg-red-50 border border-red-100 rounded-lg p-3">' +
      '<p class="text-[10px] uppercase tracking-wide text-red-500 font-bold mb-1">Cooking Request</p>' +
      '<p class="text-red-700 text-sm font-medium leading-relaxed" id="notifyInstructionsText"></p>' +
      '</div></div>' +
      '<div class="bg-white p-4 rounded-lg border border-gray-100 shadow-sm mt-2" id="notifyBillSection">' +
      '<div class="flex justify-center items-center gap-2 text-gray-500 text-sm mb-1">' +
      '<span>Time</span><span class="font-medium text-gray-900" id="notifyTime">--:--</span></div>' +
      '<div class="flex justify-center items-center gap-2 pt-2 border-t border-gray-100 mt-2">' +
      '<span class="text-gray-900 font-bold text-lg">Total Bill</span>' +
      '<span class="text-xl font-bold text-gray-900" id="notifyTotal">₹0</span></div></div></div>' +
      '<div class="p-4 bg-white border-t border-gray-100" id="notifyFooter">' +
      '<div id="notifyDeliveryFooter" class="hidden flex gap-2 w-full">' +
      '<button id="deliveryAcceptButton" type="button" class="flex-1 bg-primary hover:bg-primary-hover text-white font-bold py-3.5 rounded-xl shadow-lg transition-all active:scale-[0.98] flex items-center justify-center gap-2">' +
      '<span class="material-symbols-outlined">print_connect</span><span>Accept &amp; Send KOT</span></button>' +
      '<button id="cancelDeliveryButton" type="button" class="shrink-0 flex items-center justify-center gap-1 bg-red-500 hover:bg-red-600 text-white font-bold py-3.5 px-4 rounded-xl shadow-lg transition-all active:scale-[0.98]">' +
      '<span class="material-symbols-outlined text-[20px]">close</span><span>Cancel</span></button>' +
      '</div>' +
      '<button id="actionButton" type="button" class="hidden w-full bg-primary text-white font-bold py-3.5 rounded-xl">OK</button>' +
      '</div></div></div>';

    document.body.insertAdjacentHTML('beforeend', modalHtml);
    scheduleOrderPoll(true);
    document.addEventListener('visibilitychange', function () {
      if (document.visibilityState === 'visible') checkForNewOrders();
    });
  }

  async function checkForNewOrders() {
    if (pollInFlight) {
      if (!pollWorker) scheduleOrderPoll(false);
      return;
    }
    pollInFlight = true;
    try {
      var res = await fetch('ha-api/check-new-orders.php', {
        method: 'GET',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      });
      var data = await res.json();
      if (!data.success) return;

      if (data.hotel_name) {
        var titleEl = document.getElementById('notifyHotelName');
        var nextTitle = data.hotel_name + ' · Delivery';
        if (titleEl && titleEl.textContent !== nextTitle) titleEl.textContent = nextTitle;
      }

      var combinedQueue = [];
      if (data.delivery_orders && data.delivery_orders.length) {
        combinedQueue = data.delivery_orders.map(function (d) {
          return Object.assign({}, d, { type: 'delivery' });
        });
      }

      var nextSignature = buildQueueSignature(combinedQueue);
      if (nextSignature === lastQueueSignature) return;
      lastQueueSignature = nextSignature;
      processingQueue = combinedQueue;

      if (combinedQueue.length > 0) {
        if (
          !currentNotification ||
          !processingQueue.find(function (n) {
            return getNotifId(n) === getNotifId(currentNotification);
          })
        ) {
          showNextNotification();
        }
      } else if (currentNotification) {
        showNextNotification();
      }
    } catch (e) {
      /* silent */
    } finally {
      pollInFlight = false;
      if (!pollWorker) scheduleOrderPoll(false);
    }
  }

  function showNextNotification() {
    var modal = document.getElementById('orderNotificationModal');
    if (!modal) return;

    if (processingQueue.length === 0) {
      currentNotification = null;
      stopSound();
      modal.classList.add('hidden');
      modal.classList.remove('flex');
      return;
    }

    var nextNotif = processingQueue[0];
    if (
      currentNotification &&
      getNotifId(nextNotif) === getNotifId(currentNotification) &&
      !modal.classList.contains('hidden')
    ) {
      return;
    }

    currentNotification = nextNotif;
    renderDeliveryOrderModal(currentNotification);
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    playSound();

    if ('Notification' in window && Notification.permission === 'granted') {
      try {
        new Notification('New FoodMitra order', {
          body: '#' + (currentNotification.order_number || '') + ' · ' + (currentNotification.customer_name || ''),
        });
      } catch (e) {}
    }
  }

  function renderDeliveryOrderModal(order) {
    document.getElementById('notifyHeaderTitle').textContent = 'Delivery Order';
    document.getElementById('notifyOrderId').textContent = '#' + order.order_number;
    document.getElementById('notifyDiningType').textContent = 'Customer';
    document.getElementById('notifyDiningNumber').textContent = order.customer_name || 'Guest';

    var date = new Date(order.created_at);
    document.getElementById('notifyTime').textContent = isNaN(date.getTime())
      ? String(order.created_at || '')
      : date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    document.getElementById('notifyTotal').textContent = '₹' + Math.round(Number(order.total_amount) || 0);

    var itemsList = document.getElementById('notifyItemsList');
    var addr = order.delivery_address || '';
    var phone = order.customer_phone || '';
    var pay = (order.payment_method || '').toUpperCase();
    itemsList.innerHTML =
      '<div class="rounded-lg border border-gray-100 bg-white p-3 space-y-1 mb-3 text-sm">' +
      (phone ? '<p><span class="text-gray-500">Phone:</span> <strong>' + escapeHtml(phone) + '</strong></p>' : '') +
      (addr ? '<p><span class="text-gray-500">Address:</span> ' + escapeHtml(addr) + '</p>' : '') +
      (pay ? '<p><span class="text-gray-500">Pay:</span> ' + escapeHtml(pay) + '</p>' : '') +
      '</div>';

    try {
      var cart = typeof order.items === 'string' ? JSON.parse(order.items) : order.items;
      var itemsArray = Array.isArray(cart) ? cart : Object.values(cart || {});
      itemsArray.forEach(function (cartItem) {
        var name = cartItem.name || (cartItem.item && cartItem.item.item_name) || 'Item';
        var qty = cartItem.qty || cartItem.quantity || 1;
        var price = Number(cartItem.price || 0);
        var line = Math.round(price * qty);
        itemsList.insertAdjacentHTML(
          'beforeend',
          '<div class="flex items-start bg-white p-3 rounded-lg border border-gray-100 shadow-sm">' +
            '<span class="h-6 w-6 rounded bg-primary-soft text-primary font-bold text-xs flex items-center justify-center mr-3 shrink-0">' +
            qty +
            'x</span>' +
            '<div class="flex-1 min-w-0"><p class="text-sm font-semibold text-gray-800 leading-tight">' +
            escapeHtml(String(name)) +
            '</p></div>' +
            '<span class="text-sm font-bold text-gray-900 ml-2">₹' +
            line +
            '</span></div>'
        );
      });
    } catch (e) {}

    var note = order.cooking_request || '';
    var instContainer = document.getElementById('notifyInstructionsContainer');
    if (note) {
      document.getElementById('notifyInstructionsText').textContent = note;
      instContainer.classList.remove('hidden');
    } else {
      instContainer.classList.add('hidden');
    }

    setDeliveryFooterVisible(true);
    var acceptBtn = document.getElementById('deliveryAcceptButton');
    var cancelBtn = document.getElementById('cancelDeliveryButton');
    if (acceptBtn) acceptBtn.onclick = function () { acceptDeliveryOrder(); };
    if (cancelBtn) cancelBtn.onclick = function () { cancelDeliveryOrder(); };
  }

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  window.cancelDeliveryOrder = async function () {
    if (!currentNotification || currentNotification.type !== 'delivery') return;
    if (!confirm('Cancel this order? The customer will be notified.')) return;

    stopSound();
    var modal = document.getElementById('orderNotificationModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');

    try {
      var response = await fetch('ha-api/cancel-order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ order_id: currentNotification.order_id }),
      });
      var result = await response.json();
      if (!result.success) alert('Failed to cancel: ' + (result.error || 'Unknown error'));
    } catch (e) {
      alert('Connection error: ' + e.message);
    }
    currentNotification = null;
    invalidateOrderPollCache();
  };

  window.acceptDeliveryOrder = async function () {
    if (!currentNotification || currentNotification.type !== 'delivery') return;
    stopSound();
    var modal = document.getElementById('orderNotificationModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');

    try {
      var response = await fetch('ha-api/accept-order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ order_id: currentNotification.order_id }),
      });
      var result = await response.json();
      if (result.success) {
        var url = result.print_url || 'print-online-kot.php?id=' + currentNotification.order_id;
        window.open(url, '_blank', 'width=400,height=600');
      } else {
        alert('Failed to accept: ' + (result.error || 'Unknown error'));
      }
    } catch (e) {
      alert('Connection error: ' + e.message);
    }
    invalidateOrderPollCache();
  };

  init();
})();
