<?php

declare(strict_types=1);

namespace App\UI\Http\Rest;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DemoController extends AbstractController
{
    #[Route('/', name: 'demo_home', methods: ['GET'])]
    public function index(): Response
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nexi / Przelewy24 Payment Gateway Demo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .badge-CAPTURED { background-color: #10B981; color: white; }
        .badge-PENDING { background-color: #F59E0B; color: white; }
        .badge-REJECTED { background-color: #EF4444; color: white; }
        .badge-REFUNDED { background-color: #6B7280; color: white; }
    </style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen font-sans">
    <header class="border-b border-slate-800 bg-slate-950/80 backdrop-blur sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-rose-500 to-indigo-600 flex items-center justify-center font-bold text-xl shadow-lg shadow-rose-500/20">
                    P24
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight">Payment Gateway Demo</h1>
                    <p class="text-xs text-slate-400">Przelewy24 & Polskie ePłatności | Nexi Group</p>
                </div>
            </div>
            <div class="flex items-center space-x-4 text-xs font-mono">
                <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 mr-2 animate-pulse"></span>
                    Symfony 7.4
                </span>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-blue-500/10 text-blue-400 border border-blue-500/20">
                    PostgreSQL 16
                </span>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-amber-500/10 text-amber-400 border border-amber-500/20">
                    RabbitMQ 3
                </span>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-teal-500/10 text-teal-400 border border-teal-500/20">
                    Elasticsearch 8.15
                </span>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-8 grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left Column: Checkout Simulator -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-slate-800/60 border border-slate-700/60 rounded-2xl p-6 shadow-xl backdrop-blur">
                <h2 class="text-lg font-bold flex items-center mb-4 text-slate-200">
                    <i class="fa-solid fa-cart-shopping text-rose-400 mr-2"></i>
                    Fast Checkout Simulator
                </h2>
                <form id="checkoutForm" class="space-y-4 text-sm">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Customer Email</label>
                        <input type="email" id="email" value="anna.kowalska@example.com" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-200 focus:outline-none focus:border-rose-500">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Amount (PLN)</label>
                            <input type="number" id="amount" value="149.99" step="0.01" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-200 focus:outline-none focus:border-rose-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Merchant ID</label>
                            <input type="number" id="merchantId" value="100234" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-200 focus:outline-none focus:border-rose-500">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Payment Method</label>
                        <div class="grid grid-cols-3 gap-2">
                            <button type="button" onclick="selectMethod('BLIK')" class="method-btn py-2 px-3 rounded-lg border border-rose-500 bg-rose-500/10 font-bold text-center text-xs" data-method="BLIK">BLIK</button>
                            <button type="button" onclick="selectMethod('CARD')" class="method-btn py-2 px-3 rounded-lg border border-slate-700 bg-slate-900 text-center text-xs" data-method="CARD">Card</button>
                            <button type="button" onclick="selectMethod('PBL_MBANK')" class="method-btn py-2 px-3 rounded-lg border border-slate-700 bg-slate-900 text-center text-xs" data-method="PBL_MBANK">mBank PBL</button>
                        </div>
                    </div>

                    <div id="blikSection" class="p-3 bg-rose-950/30 border border-rose-500/30 rounded-xl space-y-2">
                        <label class="block text-xs font-semibold text-rose-300">BLIK 6-Digit Code</label>
                        <input type="text" id="blikCode" value="123456" maxlength="6" class="w-full bg-slate-900 border border-rose-500/50 rounded-lg px-3 py-2 text-center font-mono text-xl tracking-widest text-white focus:outline-none">
                        <p class="text-[11px] text-slate-400">💡 Tip: Codes starting with <code class="text-amber-400">777</code> simulate user rejection; others simulate success.</p>
                    </div>

                    <button type="button" onclick="executePayment()" id="payBtn" class="w-full bg-gradient-to-r from-rose-500 to-indigo-600 hover:from-rose-600 hover:to-indigo-700 text-white font-bold py-3 px-4 rounded-xl shadow-lg shadow-rose-500/25 transition duration-200 flex items-center justify-center">
                        <i class="fa-solid fa-lock mr-2"></i> Pay & Authorize
                    </button>
                </form>
            </div>

            <!-- Fraud Velocity Checker -->
            <div class="bg-slate-800/60 border border-slate-700/60 rounded-2xl p-6 shadow-xl backdrop-blur">
                <h2 class="text-lg font-bold flex items-center mb-3 text-slate-200">
                    <i class="fa-solid fa-shield-halved text-teal-400 mr-2"></i>
                    Elasticsearch Anti-Fraud Engine
                </h2>
                <p class="text-xs text-slate-400 mb-4">Calculates real-time velocity score across IP and Email using Elasticsearch aggregation counters.</p>
                <button type="button" onclick="checkFraud()" class="w-full bg-slate-700 hover:bg-slate-600 text-slate-200 font-semibold py-2 px-3 rounded-lg text-xs transition">
                    Run Risk Evaluation on Current Input
                </button>
                <div id="fraudResult" class="mt-3 hidden p-3 rounded-xl border text-xs space-y-1"></div>
            </div>
        </div>

        <!-- Middle & Right Columns: Real-Time Search & Aggregations -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Search & Filters -->
            <div class="bg-slate-800/60 border border-slate-700/60 rounded-2xl p-6 shadow-xl backdrop-blur">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-4">
                    <h2 class="text-lg font-bold flex items-center text-slate-200">
                        <i class="fa-solid fa-magnifying-glass text-indigo-400 mr-2"></i>
                        Live Elasticsearch Search
                    </h2>
                    <div class="flex items-center space-x-2">
                        <button onclick="seedData()" class="px-3 py-1.5 bg-indigo-600/20 hover:bg-indigo-600/40 text-indigo-300 border border-indigo-500/30 rounded-lg text-xs font-semibold">
                            <i class="fa-solid fa-database mr-1"></i> Seed 20 Mock Transactions
                        </button>
                        <button onclick="loadTransactions()" class="px-3 py-1.5 bg-slate-700 hover:bg-slate-600 text-slate-200 rounded-lg text-xs">
                            <i class="fa-solid fa-rotate mr-1"></i> Refresh
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
                    <input type="text" id="searchQuery" placeholder="Search by email, session ID, description..." class="bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-slate-200 focus:outline-none">
                    <select id="statusFilter" class="bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-slate-200 focus:outline-none">
                        <option value="">All Statuses</option>
                        <option value="CAPTURED">CAPTURED</option>
                        <option value="PENDING">PENDING</option>
                        <option value="REJECTED">REJECTED</option>
                        <option value="REFUNDED">REFUNDED</option>
                    </select>
                    <button onclick="loadTransactions()" class="bg-indigo-600 hover:bg-indigo-500 text-white font-semibold py-2 px-4 rounded-lg text-xs">
                        Filter Elasticsearch
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="border-b border-slate-700 text-slate-400">
                                <th class="py-2.5 px-3">Session ID</th>
                                <th class="py-2.5 px-3">Customer</th>
                                <th class="py-2.5 px-3">Amount</th>
                                <th class="py-2.5 px-3">Method</th>
                                <th class="py-2.5 px-3">Status</th>
                                <th class="py-2.5 px-3">Created</th>
                            </tr>
                        </thead>
                        <tbody id="transactionsTableBody" class="divide-y divide-slate-800 text-slate-300">
                            <tr>
                                <td colspan="6" class="py-4 text-center text-slate-500">Loading transactions...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Activity Log -->
            <div class="bg-slate-800/60 border border-slate-700/60 rounded-2xl p-6 shadow-xl backdrop-blur">
                <h3 class="text-sm font-bold text-slate-300 mb-2 flex items-center">
                    <i class="fa-solid fa-terminal text-emerald-400 mr-2"></i> Event Stream & Architecture Traces
                </h3>
                <pre id="activityLog" class="bg-slate-950 p-4 rounded-xl text-[11px] font-mono text-emerald-400 overflow-x-auto max-h-48 border border-slate-800">// Gateway initialized. Ready to process payment events...</pre>
            </div>
        </div>
    </main>

    <script>
        let currentMethod = 'BLIK';
        const CRC_KEY = 'crc_secret_demo_key_998877';

        function selectMethod(method) {
            currentMethod = method;
            document.querySelectorAll('.method-btn').forEach(btn => {
                if (btn.dataset.method === method) {
                    btn.className = 'method-btn py-2 px-3 rounded-lg border border-rose-500 bg-rose-500/10 font-bold text-center text-xs text-rose-400';
                } else {
                    btn.className = 'method-btn py-2 px-3 rounded-lg border border-slate-700 bg-slate-900 text-center text-xs text-slate-300';
                }
            });
            document.getElementById('blikSection').style.display = method === 'BLIK' ? 'block' : 'none';
        }

        function log(msg) {
            const el = document.getElementById('activityLog');
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
                ? 'mt-3 p-3 rounded-xl border border-emerald-500/30 bg-emerald-950/20 text-emerald-300 text-xs space-y-1'
                : 'mt-3 p-3 rounded-xl border border-rose-500/30 bg-rose-950/20 text-rose-300 text-xs space-y-1';

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
            const q = document.getElementById('searchQuery').value;
            const status = document.getElementById('statusFilter').value;

            const url = new URL('/api/v1/payments/search', window.location.origin);
            if (q) url.searchParams.set('query', q);
            if (status) url.searchParams.set('status', status);

            try {
                const res = await fetch(url);
                const data = await res.json();
                const tbody = document.getElementById('transactionsTableBody');
                tbody.innerHTML = '';

                if (!data.items || data.items.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" class="py-4 text-center text-slate-500">No transactions match your search.</td></tr>';
                    return;
                }

                data.items.forEach(tx => {
                    const row = document.createElement('tr');
                    const badgeClass = 'badge-' + tx.status;
                    const date = tx.created_at ? new Date(tx.created_at).toLocaleTimeString() : '-';
                    const amount = (tx.amount / 100).toFixed(2);
                    row.innerHTML = `
                        <td class="py-2.5 px-3 font-mono text-[11px] text-slate-300">${tx.session_id}</td>
                        <td class="py-2.5 px-3 text-slate-400">${tx.email || '-'}</td>
                        <td class="py-2.5 px-3 font-semibold text-slate-100">${amount} ${tx.currency}</td>
                        <td class="py-2.5 px-3"><span class="px-2 py-0.5 rounded bg-slate-800 text-[10px] text-slate-300 border border-slate-700">${tx.payment_method || 'N/A'}</span></td>
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
            // Simulates creating a few sample transactions
            for (let i = 0; i < 3; i++) {
                await executePayment();
            }
        }

        window.onload = () => {
            loadTransactions();
        };
    </script>
</body>
</html>
HTML;

        return new Response($html);
    }
}
