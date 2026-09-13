/**
 * BijouxInvest — Toast Notification Engine
 * Displays flash messages as bottom banners, auto-dismissed after 5s.
 */
(function () {
    'use strict';

    const DURATION  = 5000;
    const GAP       = 12;
    const ANIM_IN   = 380;
    const ANIM_OUT  = 320;

    const ICONS = {
        success: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
        danger:  '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
        warning: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        info:    '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
    };

    const COLORS = {
        success: { text: '#4ade80', border: '#4ade80', bg: 'rgba(74,222,128,0.08)' },
        danger:  { text: '#f87171', border: '#f87171', bg: 'rgba(248,113,113,0.08)' },
        warning: { text: '#fbbf24', border: '#fbbf24', bg: 'rgba(251,191,36,0.08)'  },
        info:    { text: '#60a5fa', border: '#60a5fa', bg: 'rgba(96,165,250,0.08)'  },
    };

    let container = null;

    function getContainer() {
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            Object.assign(container.style, {
                position:      'fixed',
                bottom:        '24px',
                left:          '50%',
                transform:     'translateX(-50%)',
                zIndex:        '99999',
                display:       'flex',
                flexDirection: 'column-reverse',
                alignItems:    'center',
                gap:           GAP + 'px',
                pointerEvents: 'none',
                width:         'min(540px, calc(100vw - 32px))',
            });
            document.body.appendChild(container);
        }
        return container;
    }

    function buildToast(type, text) {
        const c = COLORS[type] || COLORS.info;
        const icon = ICONS[type] || ICONS.info;

        const toast = document.createElement('div');
        toast.className = 'bj-toast bj-toast--' + type;

        Object.assign(toast.style, {
            display:          'flex',
            alignItems:       'flex-start',
            gap:              '12px',
            padding:          '14px 18px',
            borderRadius:     '14px',
            background:       'rgba(18,18,30,0.92)',
            backdropFilter:   'blur(16px)',
            WebkitBackdropFilter: 'blur(16px)',
            border:           '1px solid ' + c.border + '55',
            borderLeft:       '4px solid ' + c.border,
            boxShadow:        '0 8px 32px rgba(0,0,0,0.4), 0 0 0 1px rgba(255,255,255,0.04)',
            color:            '#e2e8f0',
            fontFamily:       'inherit',
            fontSize:         '0.93rem',
            lineHeight:       '1.4',
            width:            '100%',
            pointerEvents:    'all',
            cursor:           'pointer',
            userSelect:       'none',
            willChange:       'transform, opacity',
            position:         'relative',
            overflow:         'hidden',
            transition:       'transform ' + ANIM_IN + 'ms cubic-bezier(0.34,1.56,0.64,1), opacity ' + ANIM_IN + 'ms ease',
            transform:        'translateY(80px)',
            opacity:          '0',
        });

        const iconWrap = document.createElement('span');
        Object.assign(iconWrap.style, {
            color:      c.text,
            flexShrink: '0',
            marginTop:  '1px',
            display:    'flex',
        });
        iconWrap.innerHTML = icon;

        const textEl = document.createElement('span');
        textEl.style.flex = '1';
        textEl.textContent = text;

        const bar = document.createElement('span');
        Object.assign(bar.style, {
            position:        'absolute',
            bottom:          '0',
            left:            '0',
            height:          '3px',
            borderRadius:    '0 0 14px 14px',
            background:      c.border,
            width:           '100%',
            transformOrigin: 'left center',
            transform:       'scaleX(1)',
            transition:      'transform ' + DURATION + 'ms linear',
        });

        const closeBtn = document.createElement('button');
        Object.assign(closeBtn.style, {
            background:  'none',
            border:      'none',
            color:       '#94a3b8',
            cursor:      'pointer',
            padding:     '0',
            lineHeight:  '1',
            flexShrink:  '0',
            marginTop:   '1px',
            fontSize:    '1rem',
            transition:  'color 0.15s',
        });
        closeBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
        closeBtn.setAttribute('aria-label', 'Fermer');
        closeBtn.addEventListener('mouseenter', function() { closeBtn.style.color = '#e2e8f0'; });
        closeBtn.addEventListener('mouseleave', function() { closeBtn.style.color = '#94a3b8'; });

        toast.appendChild(iconWrap);
        toast.appendChild(textEl);
        toast.appendChild(closeBtn);
        toast.appendChild(bar);

        return { toast: toast, bar: bar, closeBtn: closeBtn };
    }

    function showToast(type, text) {
        var cont = getContainer();
        var parts = buildToast(type, text);
        var toast = parts.toast, bar = parts.bar, closeBtn = parts.closeBtn;
        cont.appendChild(toast);

        requestAnimationFrame(function() {
            requestAnimationFrame(function() {
                toast.style.transform = 'translateY(0)';
                toast.style.opacity   = '1';
            });
        });

        var started = false;
        setTimeout(function() {
            if (!started) { started = true; bar.style.transform = 'scaleX(0)'; }
        }, 60);

        var dismissed = false;
        function dismiss() {
            if (dismissed) return;
            dismissed = true;
            toast.style.transition = 'transform ' + ANIM_OUT + 'ms ease, opacity ' + ANIM_OUT + 'ms ease';
            toast.style.transform  = 'translateY(80px)';
            toast.style.opacity    = '0';
            setTimeout(function() {
                if (toast.parentNode) toast.parentNode.removeChild(toast);
            }, ANIM_OUT + 50);
        }

        var timer = setTimeout(dismiss, DURATION);
        closeBtn.addEventListener('click', function(e) { e.stopPropagation(); clearTimeout(timer); dismiss(); });
        toast.addEventListener('click', function() { clearTimeout(timer); dismiss(); });
    }

    window.BijouxToast = { show: showToast };

    function initFlash() {
        var msgs = window.__flashMessages;
        if (!Array.isArray(msgs) || msgs.length === 0) return;
        msgs.forEach(function(m, i) {
            setTimeout(function() {
                showToast(m.type || 'info', m.text || '');
            }, i * 200);
        });
        delete window.__flashMessages;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFlash);
    } else {
        initFlash();
    }
})();
