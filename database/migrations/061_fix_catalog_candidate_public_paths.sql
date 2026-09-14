UPDATE product_image_reviews
SET candidate_path=REPLACE(candidate_path,'/product-image-candidates/','/assets/product-images/')
WHERE candidate_path LIKE '/product-image-candidates/%'
  AND review_status<>'approved';
