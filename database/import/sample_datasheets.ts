export const datasheetsByCategory: Record<string, unknown[]> = {
  "linea-residenziale-r32": [
    {
      "subcategoryName": "Mono Split",
      "subcategorySlug": "linea-residenziale-r32-mono-split",
      "sourceUrl": "https://www.idemaclima.it/example/",
      "products": [
        {
          "name": "ISPT-R32",
          "image": "https://www.idemaclima.it/wp-content/uploads/immagini/UI_ISPT.png",
          "description": "Sistema R32",
          "unavailable": false,
          "groups": [
            {
              "label": "SCHEDE TECNICHE",
              "files": [
                {"model": "ISPT-25-R32", "url": "/assets/ISPT-25-R32.pdf"},
                {"model": "ISPT-35-R32", "url": "/assets/ISPT-35-R32.pdf"}
              ]
            },
            {
              "label": "MANUALI",
              "files": [
                {"model": "INSTALLAZIONE", "url": "/assets/IM_ISPT_R32.pdf"},
                {"model": "TELECOMANDO (RG10L1(G2HS))", "url": "/assets/UM_RG10L1G2HS.pdf"}
              ]
            }
          ]
        }
      ]
    }
  ]
};
