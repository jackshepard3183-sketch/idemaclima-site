UPDATE events
SET cover_image = CASE audience
    WHEN 'cat' THEN '/idemaclima/public/brand-assets/campus-eventi-cat.webp.php'
    ELSE '/idemaclima/public/brand-assets/campus-eventi-aperti.webp.php'
END
WHERE audience IN ('public','cat');
