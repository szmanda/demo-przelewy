let currentMethod = 'BLIK';
const CRC_KEY = 'crc_secret_demo_key_998877';

function selectMethod(method) {
    currentMethod = method;
    document.querySelectorAll('.method-btn').forEach(btn => {
        if (btn.dataset.method === method) {
            btn.className = 'method-btn py-2 px-3 rounded-lg border border-emerald-800 bg-emerald-50 font-bold text-center text-xs text-emerald-900 transition';
        } else {
            btn.className = 'method-btn py-2 px-3 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-center text-xs text-slate-700 transition';
        }
    });
    const blikSection = document.getElementById('blikSection');
    if (blikSection) {
        blikSection.style.display = method === 'BLIK' ? 'block' : 'none';
    }
}

function log(msg) {
    const el = document.getElementById('activityLog');
    if (!el) return;
    const time = new Date().toISOString().substring(11, 19);
    el.textContent = `[${time}] ` + msg + "\n" + el.textContent;
}

async function sha384(str) {
    const msgBuffer = new TextEncoder().encode(str);
    const hashBuffer = await crypto.subtle.digest('SHA-384', msgBuffer);
    const hashArray = Array.from(new Uint8Array(hashBuffer));
    return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
}

async function executePayment() {
    const email = document.getElementById('email').value;
    const amountMajor = parseFloat(document.getElementById('amount').value);
    const amountMinor = Math.round(amountMajor * 100);
    const merchantId = parseInt(document.getElementById('merchantId').value);
    const sessionId = 'sess_' + Math.random().toString(36).substring(2, 12);

    log(`1. Initiating checkout: sessionId=${sessionId}, amount=${amountMajor} PLN`);

    // Compute SHA-384 signature
    const signPayload = JSON.stringify({
        sessionId: sessionId,
        merchantId: merchantId,
        amount: amountMinor,
        currency: 'PLN',
        crc: CRC_KEY
    });
    const signature = await sha384(signPayload);

    // Register session
    const regRes = await fetch('/api/v1/payments/register', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            sessionId: sessionId,
            merchantId: merchantId,
            amount: amountMinor,
            currency: 'PLN',
            description: `Online Order #${Math.floor(Math.random()*9000+1000)}`,
            email: email,
            clientIp: '195.150.9.37',
            signature: signature
        })
    });

    const regData = await regRes.json();
    if (!regRes.ok) {
        log(`❌ Registration failed: ` + JSON.stringify(regData));
        return;
    }

    log(`2. ✅ Session registered: status=${regData.data.status}, token=${regData.data.token}`);

    if (currentMethod === 'BLIK') {
        const blikCode = document.getElementById('blikCode').value;
        log(`3. Authorizing BLIK code: ${blikCode}...`);

        const blikRes = await fetch('/api/v1/payments/blik/authorize', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                sessionId: sessionId,
                merchantId: merchantId,
                blikCode: blikCode
            })
        });
        const blikData = await blikRes.json();
        log(`4. BLIK Result: ${blikData.status} (Final status: ${blikData.data.status})`);
    } else {
        // Simulate Bank IPN Notification
        log(`3. Simulating Bank Webhook Notification (IPN)...`);
        const notifPayload = JSON.stringify({
            sessionId: sessionId,
            orderId: 987654,
            amount: amountMinor,
            currency: 'PLN',
            crc: CRC_KEY
        });
        const notifSig = await sha384(notifPayload);

        const notifRes = await fetch('/api/v1/payments/notify', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                sessionId: sessionId,
                merchantId: merchantId,
                orderId: 987654,
                amount: amountMinor,
                currency: 'PLN',
                paymentMethod: currentMethod,
                signature: notifSig
            })
        });
        const notifData = await notifRes.json();
        log(`4. ✅ Payment Captured: status=${notifData.data.status}`);
    }

    setTimeout(loadTransactions, 300);
}

async function checkFraud() {
    const email = document.getElementById('email').value;
    const amountMinor = Math.round(parseFloat(document.getElementById('amount').value) * 100);

    const res = await fetch('/api/v1/fraud/check', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            clientIp: '195.150.9.37',
            email: email,
            amount: amountMinor
        })
    });

    const data = await res.json();
    const el = document.getElementById('fraudResult');
    el.classList.remove('hidden');

    const isAllow = data.data.recommendation === 'ALLOW';
    el.className = isAllow 
        ? 'mt-3 p-3 rounded-lg border border-emerald-300 bg-emerald-50 text-emerald-900 text-xs space-y-1 font-medium'
        : 'mt-3 p-3 rounded-lg border border-red-300 bg-red-50 text-red-900 text-xs space-y-1 font-medium';

    el.innerHTML = `
        <div class="font-bold flex justify-between">
            <span>Risk Level: ${data.data.riskLevel} (Score: ${data.data.score}/100)</span>
            <span class="font-mono uppercase">${data.data.recommendation}</span>
        </div>
        <div>IP attempts (1m): ${data.data.metrics.ipCount1m} | Email attempts (15m): ${data.data.metrics.emailCount15m}</div>
    `;
    log(`🛡️ Fraud check: Score=${data.data.score}, Level=${data.data.riskLevel}, Decision=${data.data.recommendation}`);
}

async function loadTransactions() {
    const queryEl = document.getElementById('searchQuery');
    const statusEl = document.getElementById('statusFilter');
    const q = queryEl ? queryEl.value : '';
    const status = statusEl ? statusEl.value : '';

    const url = new URL('/api/v1/payments/search', window.location.origin);
    if (q) url.searchParams.set('query', q);
    if (status) url.searchParams.set('status', status);

    try {
        const res = await fetch(url);
        const data = await res.json();
        const tbody = document.getElementById('transactionsTableBody');
        if (!tbody) return;
        tbody.innerHTML = '';

        if (!data.items || data.items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="py-4 text-center text-slate-500">No transactions match your search.</td></tr>';
            return;
        }

        data.items.forEach(tx => {
            const row = document.createElement('tr');
            row.className = 'hover:bg-slate-50/80 transition-colors';
            const badgeClass = 'badge-' + tx.status;
            const date = tx.created_at ? new Date(tx.created_at).toLocaleTimeString() : '-';
            const amount = (tx.amount / 100).toFixed(2);
            row.innerHTML = `
                <td class="py-2.5 px-3 font-mono text-[11px] text-slate-700 font-medium">${tx.session_id}</td>
                <td class="py-2.5 px-3 text-slate-600">${tx.email || '-'}</td>
                <td class="py-2.5 px-3 font-semibold text-slate-900">${amount} ${tx.currency}</td>
                <td class="py-2.5 px-3"><span class="px-2 py-0.5 rounded bg-slate-100 text-[10px] text-slate-700 border border-slate-200 font-medium">${tx.payment_method || 'N/A'}</span></td>
                <td class="py-2.5 px-3"><span class="px-2 py-0.5 rounded text-[10px] font-bold ${badgeClass}">${tx.status}</span></td>
                <td class="py-2.5 px-3 text-slate-500">${date}</td>
            `;
            tbody.appendChild(row);
        });
    } catch (err) {
        console.error(err);
    }
}

async function seedData() {
    log('⚡ Triggering mock transaction generator...');
    for (let i = 0; i < 3; i++) {
        await executePayment();
    }
}

document.addEventListener('DOMContentLoaded', () => {
    loadTransactions();
});
