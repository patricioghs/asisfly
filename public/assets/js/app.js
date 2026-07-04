document.getElementById('themeToggle')?.addEventListener('click', () => {
  const root = document.documentElement;
  const next = root.dataset.bsTheme === 'dark' ? 'light' : 'dark';
  root.dataset.bsTheme = next;
  document.cookie = `AsisFly_theme=${next};path=/;max-age=31536000`;
});

const sidebarOpen = document.getElementById('sidebarOpen');
const sidebarClose = document.getElementById('sidebarClose');
const sidebarScrim = document.getElementById('sidebarScrim');

const closeSidebar = () => document.body.classList.remove('sidebar-open');

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
