import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';
import '../../css/app.css';

const root = document.getElementById('stegolock-root');
if (!root) throw new Error('#stegolock-root not found');
createRoot(root).render(<StrictMode><App /></StrictMode>);
