import 'bootstrap-icons/font/bootstrap-icons.css';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router';
import App from './App.jsx';
import { readInitialData } from './utils/initialData.js';

const initialData = readInitialData(document);

// The server rendered <title>/robots for crawlers and first paint. From here on each
// page renders its own via React 19 document metadata, so remove the server copies.
document.querySelectorAll('head [data-pacms-head]').forEach((element) => element.remove());

createRoot(document.getElementById('app')).render(
    <StrictMode>
        <BrowserRouter>
            <App initialData={initialData} />
        </BrowserRouter>
    </StrictMode>,
);
