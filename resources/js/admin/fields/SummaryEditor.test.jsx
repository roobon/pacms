import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import SummaryEditor from './SummaryEditor.jsx';

describe('SummaryEditor', () => {
    it('offers only bold, italic and link', async () => {
        render(<SummaryEditor value="<p>Hello <strong>world</strong></p>" onChange={() => {}} />);

        const toolbar = await screen.findByRole('toolbar', { name: 'Summary formatting' });
        const buttons = [...toolbar.querySelectorAll('button')].map((b) => b.textContent.trim());
        expect(buttons).toEqual(['Bold (Ctrl+B)', 'Italic (Ctrl+I)', 'Link']);
        expect(screen.getByRole('textbox').innerHTML).toContain('<strong>world</strong>');
    });
});
