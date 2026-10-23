<x-app-layout :title="__('دستیار گفتگویی مالی هوش مصنوعی (Financial Copilot)')">
    <x-show-message-bags />

    <div class="mx-auto max-w-7xl h-[calc(100vh-140px)] flex flex-col md:flex-row gap-4"
         x-data="copilotChat({
             conversationId: {{ $activeConversation ? $activeConversation->id : 'null' }},
             initialMessages: {{ json_encode($activeConversation ? $activeConversation->messages : []) }},
             chatUrl: '{{ route('copilot.chat') }}',
             csrf: '{{ csrf_token() }}'
         })">

        <!-- Sidebar: Conversation History -->
        <div class="w-full md:w-80 flex flex-col bg-base-100 rounded-2xl shadow-sm border border-base-200 p-4 shrink-0">
            <div class="flex items-center justify-between pb-3 border-b border-base-200">
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-emerald-500 animate-pulse"></span>
                    <h2 class="font-bold text-sm">{{ __('تاریخچه گفتگوها') }}</h2>
                </div>
                <button type="button" @click="startNewChat()" class="btn btn-primary btn-xs gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    {{ __('گفتگوی جدید') }}
                </button>
            </div>

            <!-- Conversations List -->
            <div class="flex-1 overflow-y-auto mt-3 space-y-1 text-sm">
                @forelse($conversations as $conv)
                    <div class="flex items-center justify-between group rounded-xl p-2.5 transition hover:bg-base-200 {{ ($activeConversation && $activeConversation->id === $conv->id) ? 'bg-primary/10 text-primary font-medium border border-primary/20' : 'text-base-content/80' }}">
                        <a href="{{ route('copilot.index', ['c' => $conv->id]) }}" class="truncate flex-1 text-right">
                            <span class="inline-block text-xs text-base-content/40 ml-1">💬</span>
                            {{ $conv->title }}
                        </a>
                        <button type="button" @click.prevent="deleteConversation({{ $conv->id }})" class="opacity-0 group-hover:opacity-100 text-error hover:scale-110 transition p-1" title="{{ __('حذف گفتگو') }}">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </div>
                @empty
                    <div class="text-center py-8 text-base-content/40 text-xs">
                        {{ __('هنوز گفتگویی ثبت نشده است.') }}
                    </div>
                @endforelse
            </div>

            <!-- Quick Capabilities Info -->
            <div class="pt-3 border-t border-base-200 text-xs text-base-content/60 space-y-1">
                <div class="flex items-center gap-1.5 font-medium text-base-content/80">
                    <svg class="w-4 h-4 text-primary" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"/></svg>
                    {{ __('توانمندی‌های هوش مالی دخل') }}
                </div>
                <p class="leading-relaxed text-[11px]">{{ __('استعلام زنده فروش، بدهکاران سنی، چک‌های سررسید، سلامت مالیاتی، کسری انبار و تحلیل‌های راهبردی نقدینگی بدون واسطه.') }}</p>
            </div>
        </div>

        <!-- Main Chat Area -->
        <div class="flex-1 flex flex-col bg-base-100 rounded-2xl shadow-sm border border-base-200 overflow-hidden">
            <!-- Chat Header -->
            <div class="p-3.5 border-b border-base-200 flex items-center justify-between bg-base-200/50">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-primary to-secondary flex items-center justify-center text-primary-content shadow-sm">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="font-bold text-sm md:text-base">{{ __('دستیار مالی و اقتصادی دخل (Dakhl Copilot)') }}</h1>
                            <span class="badge badge-primary badge-xs py-2 px-2 text-[10px] font-mono">{{ __('AI Grounded') }}</span>
                        </div>
                        <p class="text-xs text-base-content/60">{{ __('پاسخ‌دهی بر مبنای حقیقت قطعی دفاتر مالی، اسناد دوبل و قوانین مالیاتی ایران') }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('home.today') }}" class="btn btn-ghost btn-xs text-xs gap-1">
                        {{ __('داشبورد امروز') }}
                    </a>
                </div>
            </div>

            <!-- Messages Stream -->
            <div class="flex-1 overflow-y-auto p-4 md:p-6 space-y-4" x-ref="messagesContainer">
                <!-- Welcome Card if no messages -->
                <template x-if="messages.length === 0">
                    <div class="text-center py-6 max-w-xl mx-auto space-y-4">
                        <div class="w-16 h-16 mx-auto rounded-3xl bg-primary/10 text-primary flex items-center justify-center text-3xl shadow-inner">
                            ✨
                        </div>
                        <div>
                            <h3 class="font-bold text-lg text-base-content">{{ __('سلام همکار گرامی! چطور می‌توانم در امور مالی به شما کمک کنم؟') }}</h3>
                            <p class="text-xs text-base-content/60 mt-1 leading-relaxed">{{ __('من با تحلیل لحظه‌ای دفاتر حسابداری، فاکتورها، کاردکس انبار و سامانه مودیان، تصویر شفافی از کسب‌وکار در اختیارتان می‌گذارم.') }}</p>
                        </div>

                        <!-- Suggested Prompts -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-right pt-2">
                            <button type="button" @click="ask('فروش امروز و این هفته من چقدر بوده؟')" class="p-3 bg-base-200/60 hover:bg-base-200 rounded-xl border border-base-300 text-xs text-base-content/80 flex items-start gap-2.5 transition text-right">
                                <span class="text-base shrink-0">📊</span>
                                <div>
                                    <div class="font-medium text-base-content">{{ __('فروش امروز و این هفته') }}</div>
                                    <div class="text-[11px] text-base-content/50 mt-0.5">{{ __('آمار فروش، تعداد فاکتورها و تراز ماه') }}</div>
                                </div>
                            </button>

                            <button type="button" @click="ask('کدام مشتریان بیشترین بدهی معوق رو دارن؟')" class="p-3 bg-base-200/60 hover:bg-base-200 rounded-xl border border-base-300 text-xs text-base-content/80 flex items-start gap-2.5 transition text-right">
                                <span class="text-base shrink-0">👥</span>
                                <div>
                                    <div class="font-medium text-base-content">{{ __('مشتریان بدهکار معوق') }}</div>
                                    <div class="text-[11px] text-base-content/50 mt-0.5">{{ __('گزارش سنی مطالبات و حساب‌های بالای ۹۰ روز') }}</div>
                                </div>
                            </button>

                            <button type="button" @click="ask('چک‌های سررسید این هفته چیا هستن؟')" class="p-3 bg-base-200/60 hover:bg-base-200 rounded-xl border border-base-300 text-xs text-base-content/80 flex items-start gap-2.5 transition text-right">
                                <span class="text-base shrink-0">💳</span>
                                <div>
                                    <div class="font-medium text-base-content">{{ __('چک‌های سررسید هفته') }}</div>
                                    <div class="text-[11px] text-base-content/50 mt-0.5">{{ __('تعهدات پرداختی و دریافتی صیادی') }}</div>
                                </div>
                            </button>

                            <button type="button" @click="ask('وضعیت آمادگی مالیاتی و سامانه مودیان من چطوره؟')" class="p-3 bg-base-200/60 hover:bg-base-200 rounded-xl border border-base-300 text-xs text-base-content/80 flex items-start gap-2.5 transition text-right">
                                <span class="text-base shrink-0">⚖️</span>
                                <div>
                                    <div class="font-medium text-base-content">{{ __('وضعیت مالیاتی و مودیان') }}</div>
                                    <div class="text-[11px] text-base-content/50 mt-0.5">{{ __('نمره آمادگی، ریسک‌های ممیزی و ارزش افزوده') }}</div>
                                </div>
                            </button>

                            <button type="button" @click="ask('موجودی کدام کالاها رو به اتمامه؟')" class="p-3 bg-base-200/60 hover:bg-base-200 rounded-xl border border-base-300 text-xs text-base-content/80 flex items-start gap-2.5 transition text-right">
                                <span class="text-base shrink-0">📦</span>
                                <div>
                                    <div class="font-medium text-base-content">{{ __('کالاهای نیازمند سفارش') }}</div>
                                    <div class="text-[11px] text-base-content/50 mt-0.5">{{ __('موجودی به زیر حداقل نقطه سفارش رسیده') }}</div>
                                </div>
                            </button>

                            <button type="button" @click="ask('وضعیت کلی و خلاصه عملکرد کسب و کار من چطوره؟')" class="p-3 bg-base-200/60 hover:bg-base-200 rounded-xl border border-base-300 text-xs text-base-content/80 flex items-start gap-2.5 transition text-right">
                                <span class="text-base shrink-0">🧭</span>
                                <div>
                                    <div class="font-medium text-base-content">{{ __('تحلیل راهبردی کسب‌وکار') }}</div>
                                    <div class="text-[11px] text-base-content/50 mt-0.5">{{ __('خلاصه سود، تراز مالی و توصیه نقدینگی') }}</div>
                                </div>
                            </button>

                            <button type="button" @click="ask('۲۰۰ تومن دادم علی')" class="p-3 bg-emerald-50 hover:bg-emerald-100/70 rounded-xl border border-emerald-200 text-xs text-emerald-900 flex items-start gap-2.5 transition text-right">
                                <span class="text-base shrink-0">💸</span>
                                <div>
                                    <div class="font-medium text-emerald-950">{{ __('ثبت محاوره‌ای پرداخت/دریافت') }}</div>
                                    <div class="text-[11px] text-emerald-800/60 mt-0.5">{{ __('«۲۰۰ تومن دادم علی» یا «۳۰۰ از حسن گرفتم»') }}</div>
                                </div>
                            </button>

                            <button type="button" @click="ask('۲ تا کاور دادم رضا')" class="p-3 bg-indigo-50 hover:bg-indigo-100/70 rounded-xl border border-indigo-200 text-xs text-indigo-900 flex items-start gap-2.5 transition text-right">
                                <span class="text-base shrink-0">🔄</span>
                                <div>
                                    <div class="font-medium text-indigo-950">{{ __('ثبت تهاتر کالا با همکار') }}</div>
                                    <div class="text-[11px] text-indigo-800/60 mt-0.5">{{ __('«۲ تا کاور دادم رضا» یا «۵ تا گلس گرفتم»') }}</div>
                                </div>
                            </button>

                            <button type="button" @click="ask('به علی قیمت گلس چند دادم؟')" class="p-3 bg-amber-50 hover:bg-amber-100/70 rounded-xl border border-amber-200 text-xs text-amber-900 flex items-start gap-2.5 transition text-right">
                                <span class="text-base shrink-0">🏷️</span>
                                <div>
                                    <div class="font-medium text-amber-950">{{ __('استعلام قیمت از حافظه') }}</div>
                                    <div class="text-[11px] text-amber-800/60 mt-0.5">{{ __('«به علی قیمت گلس چند دادم؟»') }}</div>
                                </div>
                            </button>
                        </div>
                    </div>
                </template>

                <!-- Message Bubbles -->
                <template x-for="(msg, index) in messages" :key="index">
                    <div class="flex flex-col" :class="msg.role === 'user' ? 'items-end' : 'items-start'">
                        <div class="flex items-start gap-2.5 max-w-[88%] md:max-w-[78%]" :class="msg.role === 'user' ? 'flex-row-reverse' : ''">
                            <!-- Avatar -->
                            <div class="w-8 h-8 rounded-xl shrink-0 flex items-center justify-center text-xs font-bold shadow-sm"
                                 :class="msg.role === 'user' ? 'bg-primary text-primary-content' : 'bg-neutral text-neutral-content'">
                                <span x-text="msg.role === 'user' ? '👤' : '🤖'"></span>
                            </div>

                            <!-- Content -->
                            <div class="p-4 rounded-2xl text-xs md:text-sm leading-relaxed whitespace-pre-line shadow-sm"
                                 :class="msg.role === 'user'
                                     ? 'bg-primary text-primary-content rounded-tr-none'
                                     : 'bg-base-200 text-base-content rounded-tl-none border border-base-300'">
                                <div x-html="renderMarkdown(msg.content)"></div>

                                <!-- Action Card if present -->
                                <template x-if="msg.action_card">
                                    <div class="mt-3 p-3.5 bg-base-100 rounded-xl border border-primary/30 shadow-xs space-y-2.5">
                                        <div class="flex items-center justify-between">
                                            <span class="font-bold text-xs text-primary flex items-center gap-1.5">
                                                <span>⚡</span>
                                                <span x-text="msg.action_card.title"></span>
                                            </span>
                                            <span class="badge badge-xs badge-primary text-white">اقدام حسابداری</span>
                                        </div>

                                        <div class="grid grid-cols-2 gap-2 text-[11px] bg-slate-50 p-2.5 rounded-lg border border-slate-100">
                                            <template x-for="(val, label) in (msg.action_card.details || {})" :key="label">
                                                <div>
                                                    <span class="text-slate-400" x-text="label + ': '"></span>
                                                    <strong class="text-slate-800" x-text="val"></strong>
                                                </div>
                                            </template>
                                        </div>

                                        <template x-if="msg.action_card.action_url">
                                            <a :href="msg.action_card.action_url" class="btn btn-xs btn-primary w-full text-white mt-1 shadow-sm font-bold">
                                                <span x-text="msg.action_card.button_text || 'تایید و ثبت نهایی سند'"></span> →
                                            </a>
                                        </template>
                                    </div>
                                </template>

                                <!-- Source badges if any -->
                                <template x-if="msg.sources && msg.sources.length > 0">
                                    <div class="mt-3 pt-2.5 border-t border-base-content/10 flex flex-wrap gap-1.5 items-center">
                                        <span class="text-[10px] opacity-60">{{ __('مرجع اطلاعات:') }}</span>
                                        <template x-for="src in msg.sources">
                                            <a :href="src.url" class="badge badge-outline badge-xs py-1 px-2 text-[10px] hover:badge-primary transition" target="_blank">
                                                <span x-text="src.title"></span> ↗
                                            </a>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Loading Bubble -->
                <template x-if="loading">
                    <div class="flex items-start gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-neutral text-neutral-content flex items-center justify-center text-xs font-bold">
                            🤖
                        </div>
                        <div class="p-3.5 rounded-2xl bg-base-200 rounded-tl-none border border-base-300 flex items-center gap-2">
                            <span class="loading loading-dots loading-sm text-primary"></span>
                            <span class="text-xs text-base-content/60">{{ __('در حال بررسی دفاتر و پردازش فکت‌های مالی...') }}</span>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Bottom Input Bar -->
            <div class="p-3 bg-base-200/50 border-t border-base-200">
                <form @submit.prevent="submitForm()" class="flex items-center gap-2">
                    <div class="relative flex-1">
                        <input type="text"
                               x-model="inputQuery"
                               :disabled="loading"
                               placeholder="{{ __('سؤال مالی خود را بپرسید (مثلاً: فروش امروز چقدره؟ یا کدام چک‌ها این هفته سررسید میشن؟)...') }}"
                               class="input input-sm md:input-md w-full bg-base-100 rounded-xl pr-4 pl-10 text-xs md:text-sm focus:outline-primary border-base-300"
                               x-ref="queryInput" />
                        <button type="button" @click="inputQuery = ''" x-show="inputQuery.length > 0" class="absolute left-3 top-1/2 -translate-y-1/2 text-base-content/40 hover:text-base-content text-xs">
                            ✕
                        </button>
                    </div>

                    <button type="submit" :disabled="loading || inputQuery.trim() === ''" class="btn btn-primary btn-sm md:btn-md rounded-xl gap-1 shrink-0 px-4">
                        <svg class="w-4 h-4 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        <span class="hidden sm:inline">{{ __('ارسال') }}</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Alpine Chat Logic -->
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('copilotChat', (config) => ({
                conversationId: config.conversationId,
                messages: config.initialMessages || [],
                inputQuery: '',
                loading: false,
                chatUrl: config.chatUrl,
                csrf: config.csrf,

                init() {
                    this.scrollToBottom();
                },

                ask(text) {
                    this.inputQuery = text;
                    this.submitForm();
                },

                async submitForm() {
                    const text = this.inputQuery.trim();
                    if (!text || this.loading) return;

                    // Add user message to UI immediately
                    this.messages.push({
                        role: 'user',
                        content: text,
                        created_at: new Date().toISOString()
                    });

                    this.inputQuery = '';
                    this.loading = true;
                    this.scrollToBottom();

                    try {
                        const response = await fetch(this.chatUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrf
                            },
                            body: JSON.stringify({
                                message: text,
                                conversation_id: this.conversationId
                            })
                        });

                        const data = await response.json();

                        if (data.success) {
                            this.conversationId = data.conversation_id;
                            this.messages.push({
                                role: 'assistant',
                                content: data.assistant_message.content,
                                sources: data.assistant_message.sources || [],
                                action_card: data.action_card || (data.assistant_message.tool_calls ? data.assistant_message.tool_calls.action_card : null)
                            });
                        } else {
                            this.messages.push({
                                role: 'assistant',
                                content: 'متأسفانه در پردازش پاسخ خطایی رخ داد. لطفاً مجدداً تلاش فرمایید.'
                            });
                        }
                    } catch (err) {
                        this.messages.push({
                            role: 'assistant',
                            content: 'خطا در ارتباط با سرور. لطفاً اتصال اینترنت یا شبکه محلی را بررسی نمایید.'
                        });
                    } finally {
                        this.loading = false;
                        this.scrollToBottom();
                        this.$nextTick(() => {
                            if (this.$refs.queryInput) this.$refs.queryInput.focus();
                        });
                    }
                },

                startNewChat() {
                    window.location.href = '{{ route('copilot.index') }}';
                },

                async deleteConversation(id) {
                    if (!confirm('آیا از حذف این گفتگو اطمینان دارید؟')) return;

                    try {
                        const res = await fetch(`/copilot/conversations/${id}`, {
                            method: 'DELETE',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrf
                            }
                        });
                        if (res.ok) {
                            window.location.href = '{{ route('copilot.index') }}';
                        }
                    } catch (e) {
                        alert('حذف گفتگو انجام نشد.');
                    }
                },

                scrollToBottom() {
                    this.$nextTick(() => {
                        const el = this.$refs.messagesContainer;
                        if (el) el.scrollTop = el.scrollHeight;
                    });
                },

                renderMarkdown(content) {
                    if (!content) return '';

                    // Simple clean markdown parser for Persian text
                    let html = content
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;');

                    // Headers
                    html = html.replace(/^### (.*$)/gim, '<h4 class="font-bold text-sm md:text-base text-primary mb-2 mt-3">$1</h4>');
                    html = html.replace(/^#### (.*$)/gim, '<h5 class="font-bold text-xs md:text-sm text-base-content mb-1 mt-2">$1</h5>');

                    // Bold & Code
                    html = html.replace(/\*\*(.*?)\*\*/g, '<strong class="font-bold text-base-content">$1</strong>');
                    html = html.replace(/`(.*?)`/g, '<code class="px-1.5 py-0.5 rounded bg-base-300 font-mono text-xs text-primary font-semibold">$1</code>');

                    // Markdown Tables
                    const tableRegex = /\|(.+)\|\n\|(?:\s*:?-+:?\s*\|)+\n((?:\|.+[^\n]+\|\n?)+)/g;
                    html = html.replace(tableRegex, (match, headerLine, bodyLines) => {
                        const headers = headerLine.split('|').map(h => h.trim()).filter(h => h);
                        const rows = bodyLines.trim().split('\n').map(row =>
                            row.split('|').map(c => c.trim()).filter(c => c)
                        );

                        let tableHtml = '<div class="overflow-x-auto my-3"><table class="table table-xs w-full text-right border border-base-300 rounded-lg"><thead><tr class="bg-base-300/60">';
                        headers.forEach(h => { tableHtml += `<th class="p-2">${h}</th>`; });
                        tableHtml += '</tr></thead><tbody>';
                        rows.forEach(r => {
                            tableHtml += '<tr class="border-t border-base-300">';
                            r.forEach(cell => { tableHtml += `<td class="p-2">${cell}</td>`; });
                            tableHtml += '</tr>';
                        });
                        tableHtml += '</tbody></table></div>';
                        return tableHtml;
                    });

                    // Bullet lists
                    html = html.replace(/^- (.*$)/gim, '<div class="flex items-start gap-1.5 my-1"><span class="text-primary font-bold">•</span><span>$1</span></div>');

                    // Markdown links [title](url)
                    html = html.replace(/\[(.*?)\]\((.*?)\)/g, '<a href="$2" class="link link-primary font-medium hover:underline" target="_blank">$1</a>');

                    return html;
                }
            }));
        });
    </script>
</x-app-layout>
