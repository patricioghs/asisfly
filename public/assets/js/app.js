document.getElementById('themeToggle')?.addEventListener('click', () => {
  const root = document.documentElement;
  const next = root.dataset.bsTheme === 'dark' ? 'light' : 'dark';
  root.dataset.bsTheme = next;
  document.cookie = `AsisFly_theme=${next};path=/;max-age=31536000`;
});

const sidebarOpen = document.getElementById('sidebarOpen');
const sidebarClose = document.getElementById('sidebarClose');
const sidebarScrim = document.getElementById('sidebarScrim');
const sidebar = document.querySelector('.sidebar');
const activeSidebarLink = sidebar?.querySelector('.nav-link.active');
const sidebarScrollKey = 'asisfly_sidebar_scroll_top';

const closeSidebar = () => document.body.classList.remove('sidebar-open');

const saveSidebarScroll = () => {
  if (!sidebar) {
    return;
  }

  sessionStorage.setItem(sidebarScrollKey, String(sidebar.scrollTop));
};

if (sidebar) {
  const storedScroll = sessionStorage.getItem(sidebarScrollKey);
  if (storedScroll !== null) {
    requestAnimationFrame(() => {
      sidebar.scrollTop = Number.parseInt(storedScroll, 10) || 0;
    });
  } else {
    activeSidebarLink?.scrollIntoView({ block: 'center' });
  }

  sidebar.addEventListener('scroll', saveSidebarScroll, { passive: true });
  sidebar.querySelectorAll('a.nav-link').forEach((link) => {
    link.addEventListener('click', saveSidebarScroll);
  });
  window.addEventListener('beforeunload', saveSidebarScroll);
}

sidebarOpen?.addEventListener('click', () => {
  document.body.classList.add('sidebar-open');
});

sidebarClose?.addEventListener('click', closeSidebar);
sidebarScrim?.addEventListener('click', closeSidebar);

document.addEventListener('keydown', (event) => {
  if (event.key === 'Escape') {
    closeSidebar();
  }
});

const chatWindow = document.querySelector('[data-chat-window]');
const chatBottom = document.querySelector('[data-chat-bottom]');
const chatForm = document.querySelector('[data-chat-form]');
const chatSubmit = document.querySelector('[data-chat-submit]');
const chatPrompt = document.querySelector('[data-chat-prompt]');

if (chatWindow) {
  requestAnimationFrame(() => {
    if (chatBottom) {
      chatBottom.scrollIntoView({ block: 'end' });
      return;
    }

    chatWindow.scrollTop = chatWindow.scrollHeight;
  });
}

chatForm?.addEventListener('submit', (event) => {
  if (chatForm.dataset.submitting === 'true') {
    event.preventDefault();
    return;
  }

  if (chatPrompt && chatPrompt.value.trim() === '') {
    event.preventDefault();
    chatPrompt.focus();
    return;
  }

  chatForm.dataset.submitting = 'true';
  if (chatSubmit) {
    chatSubmit.disabled = true;
    chatSubmit.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Enviando';
  }
});
