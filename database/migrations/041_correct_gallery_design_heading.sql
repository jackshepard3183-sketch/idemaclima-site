-- Ripristina l'etichetta della sezione e semplifica il titolo principale.
UPDATE gallery_sections
SET eyebrow = '— Design',
    title = 'Colorazioni ISAX-R32.'
WHERE section_key = 'design';
