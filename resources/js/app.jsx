import './bootstrap';
import '../css/app.css';

import { createRoot } from 'react-dom/client';
import App from './components/App';

/**
 * Entry SPA (Etapa D §7.2).
 * Monta BrowserRouter + AuthProvider + rotas via `App.jsx`.
 */
const el = document.getElementById('app');

if (el) {
    createRoot(el).render(<App />);
}
