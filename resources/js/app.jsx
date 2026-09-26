import './bootstrap';
import '../css/app.css';

import { createRoot } from 'react-dom/client';
import App from './components/App';

const el = document.getElementById('app');
if (el) {
    createRoot(el).render(<App />);
}
