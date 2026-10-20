{{--
    🌟 SITE-11: پیشنهادِ مقدارهای قبلی در فیلدهای پنل (مثلِ تاریخچه‌ی فرمِ مرورگر).
    مرورگر فقط مقدارهای فرم‌هایی را به خاطر می‌سپارد که واقعاً «ارسال» شوند و name داشته باشند؛
    فرم‌های فیلامنت با Livewire فرستاده می‌شوند و name ندارند، پس مرورگر چیزی پیشنهاد نمی‌داد.
    این اسکریپت با ارسالِ هر فرم مقدارهای پرشده را (در localStorageِ همین مرورگر، حداکثر ۱۲ مقدار
    برای هر فیلد) نگه می‌دارد و وقتی فوکوس روی فیلد می‌رود، آن‌ها را با یک <datalist> فهرست می‌کند.
    کنار گذاشته‌ها: رمز (حتی رمزِ نمایش‌دادنی)، فیلدهای autocomplete=new-password/current-password/
    one-time-code، فایل، چک‌باکس و رادیو، جست‌وجوی داخلِ Select، و هر چیزی داخلِ [data-no-field-history].
--}}
<script>
    (() => {
        if (window.__fieldHistory) return;
        window.__fieldHistory = true;

        const STORE = 'fieldHistory:v1';
        const MAX = 12;
        const MAX_LEN = 200;
        const SKIP_TYPES = new Set(['password', 'hidden', 'file', 'checkbox', 'radio', 'range', 'color',
            'submit', 'button', 'reset', 'image', 'date', 'datetime-local', 'time', 'month', 'week']);
        const SKIP_AUTOCOMPLETE = new Set(['new-password', 'current-password', 'one-time-code']);

        const read = () => {
            try { return JSON.parse(localStorage.getItem(STORE)) || {}; } catch (e) { return {}; }
        };
        const write = (data) => {
            try { localStorage.setItem(STORE, JSON.stringify(data)); } catch (e) { /* پر یا مسدود */ }
        };

        // یک فیلد در «همه‌ی رکوردهای یک بخش» یکی است: /admin/users/5/edit و /admin/users/create ← /admin/users
        const scope = () => location.pathname
            .replace(/\/\d+(?=\/|$)/g, '/*')
            .replace(/\/(create|edit)$/, '')
            .replace(/(\/\*)+$/, '');
        const fieldName = (el) => el.id
            || ([...el.attributes].find((a) => a.name.startsWith('wire:model')) || {}).value
            || el.name;
        const keyOf = (el) => {
            const name = fieldName(el);
            return name ? scope() + '#' + name : null;
        };
        const eligible = (el) => el instanceof HTMLInputElement
            && ! SKIP_TYPES.has(el.type)
            && ! el.hasAttribute('x-bind:type') // رمزِ «نمایش‌دادنی» که موقتاً type=text می‌شود
            && ! SKIP_AUTOCOMPLETE.has((el.getAttribute('autocomplete') || '').toLowerCase())
            && ! el.disabled && ! el.readOnly
            && ! el.closest('.choices, [data-no-field-history]');

        const remember = (el) => {
            const value = (el.value || '').trim();
            const key = keyOf(el);
            if (! key || ! value || value.length > MAX_LEN) return;
            const data = read();
            data[key] = [value, ...(data[key] || []).filter((v) => v !== value)].slice(0, MAX);
            write(data);
        };

        document.addEventListener('submit', (event) => {
            event.target.querySelectorAll('input').forEach((el) => eligible(el) && remember(el));
        }, true);
        // فیلدهای بیرونِ فرم (مثلِ جست‌وجوی جدول‌ها)
        document.addEventListener('change', (event) => {
            const el = event.target;
            if (eligible(el) && ! el.form) remember(el);
        }, true);

        let seq = 0;
        document.addEventListener('focusin', (event) => {
            const el = event.target;
            if (! eligible(el)) return;
            const own = el.dataset.fieldHistoryList;
            const current = el.getAttribute('list');
            if (current && current !== own) return; // datalistِ خودِ فیلد (->datalist()) دست نمی‌خورد
            const key = keyOf(el);
            const values = key ? (read()[key] || []) : [];
            if (! values.length) return;

            const id = own || ('field-history-' + (++seq));
            let list = document.getElementById(id);
            if (! list) {
                list = document.createElement('datalist');
                list.id = id;
                document.body.appendChild(list);
            }
            list.replaceChildren(...values.map((v) => {
                const option = document.createElement('option');
                option.value = v;
                return option;
            }));
            el.dataset.fieldHistoryList = id;
            el.setAttribute('list', id); // Livewire پس از هر رندر شاید برش دارد؛ فوکوسِ بعدی دوباره می‌گذارد
        });
    })();
</script>
