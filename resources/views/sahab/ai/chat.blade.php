@extends('sahab.layouts.app')

@section('title', 'المساعد الذكي')
@section('page_title', '✨ المساعد الذكي')
@section('page_subtitle', 'اسأل أي شي عن محلّك بالعربي')

@section('content')
<div x-data="aiChat()" class="max-w-4xl mx-auto h-[calc(100vh-180px)] flex flex-col">

  {{-- Quick prompts --}}
  <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mb-4">
    <template x-for="prompt in quickPrompts">
      <button @click="ask(prompt)" class="bg-white hover:bg-sand-100 border border-sand-200 rounded-xl p-3 text-right text-sm transition">
        <span x-text="prompt"></span>
      </button>
    </template>
  </div>

  {{-- Messages --}}
  <div class="flex-1 bg-white rounded-2xl border border-sand-200 p-6 overflow-y-auto" id="messagesArea">
    <template x-for="msg in messages" :key="msg.id">
      <div class="mb-4" :class="msg.role === 'user' ? 'flex justify-end' : ''">
        <div class="max-w-[80%]"
             :class="msg.role === 'user' ? 'bg-teal-700 text-white' : 'bg-sand-100'"
             class="rounded-2xl p-4">
          <div x-text="msg.content"></div>
          <template x-if="msg.role === 'assistant'">
            <div class="text-xs text-ink-700/40 mt-2" x-text="msg.timestamp"></div>
          </template>
        </div>
      </div>
    </template>

    <template x-if="loading">
      <div class="flex gap-2 items-center mb-4">
        <div class="bg-sand-100 rounded-2xl p-4">
          <div class="flex gap-1">
            <div class="w-2 h-2 bg-teal-700 rounded-full animate-bounce"></div>
            <div class="w-2 h-2 bg-teal-700 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
            <div class="w-2 h-2 bg-teal-700 rounded-full animate-bounce" style="animation-delay: 0.4s"></div>
          </div>
        </div>
      </div>
    </template>

    <template x-if="messages.length === 0 && !loading">
      <div class="text-center py-20 text-ink-700/50">
        <div class="text-7xl mb-4">✨</div>
        <h3 class="font-bold text-2xl mb-2">مرحباً! أنا مساعد سحاب</h3>
        <p class="text-sm">اسألني عن مبيعاتك، مخزونك، موظّفينك، أو أي شي يخص محلّك</p>
      </div>
    </template>
  </div>

  {{-- Input --}}
  <div class="bg-white rounded-2xl border border-sand-200 p-3 mt-4 flex gap-2">
    <textarea x-model="input" @keydown.enter.prevent="ask(input)"
              placeholder="اكتب سؤالك..."
              rows="1"
              class="flex-1 px-4 py-2 rounded-xl resize-none focus:outline-none"></textarea>
    <button @click="ask(input)" :disabled="loading || !input.trim()"
            class="bg-teal-700 hover:bg-teal-900 disabled:bg-gray-300 text-white px-6 rounded-xl font-bold transition">
      إرسال
    </button>
  </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script>
function aiChat() {
  return {
    messages: [],
    input: '',
    loading: false,
    quickPrompts: [
      'كم بعت اليوم؟',
      'أكثر منتج مبيعاً هذا الأسبوع؟',
      'وش المخزون اللي ناقص؟',
      'كيف أداء الموظّفين هذا الشهر؟',
    ],

    async ask(question) {
      if (!question?.trim() || this.loading) return;

      this.messages.push({
        id: Date.now(),
        role: 'user',
        content: question,
      });
      this.input = '';
      this.loading = true;

      this.scrollToBottom();

      try {
        const res = await fetch('/api/sahab/ai/ask', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          },
          body: JSON.stringify({ question }),
        });
        const data = await res.json();

        this.messages.push({
          id: Date.now() + 1,
          role: 'assistant',
          content: data.answer || 'عذراً، لم أستطع الإجابة',
          timestamp: new Date().toLocaleTimeString('ar-SA'),
        });
      } catch (e) {
        this.messages.push({
          id: Date.now() + 1,
          role: 'assistant',
          content: 'عذراً، حدث خطأ في الاتصال',
          timestamp: new Date().toLocaleTimeString('ar-SA'),
        });
      } finally {
        this.loading = false;
        this.scrollToBottom();
      }
    },

    scrollToBottom() {
      setTimeout(() => {
        const area = document.getElementById('messagesArea');
        if (area) area.scrollTop = area.scrollHeight;
      }, 100);
    },
  };
}
</script>
@endpush

@endsection
