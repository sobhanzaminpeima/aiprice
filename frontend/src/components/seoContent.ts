/**
 * Visible SEO copy for the home, Cars and Properties pages. The FAQ text is
 * mirrored in backend/app/Services/Seo/SeoShell.php (FAQPage structured data);
 * keep the two in sync so the markup matches what visitors see.
 */
export const SEO_CONTENT = {
  home: {
    title: 'Buy and sell cars and property in North Cyprus',
    paragraphs: [
      'AIPrice.app is a marketplace for cars, homes and rentals across North Cyprus (KKTC): Girne (Kyrenia), Lefkoşa (Nicosia), Gazimağusa (Famagusta), İskele, Güzelyurt and Lapta.',
      'Every listing is compared with similar listings, so you see a fair price range, a price battery score and how confident the check is before you call the seller.',
    ],
    faq: [
      ['How does the AI price check work?', 'We compare the asking price with similar listings (same model, year, size and area) and show the typical range. With fewer than three similar listings we show no verdict rather than a guess.'],
      ['What is the price battery?', 'A score from 0 to 100% showing how good the price is against similar listings. A full battery means one of the best-value prices on the market.'],
      ['Is it free to list?', 'Yes. Private sellers can have two listings live for free. Dealers and estate agencies can try any plan free for a month.'],
    ],
  },
  vehicle: {
    title: 'Used and new cars for sale in North Cyprus',
    paragraphs: [
      'Find used and new cars for sale from private sellers and dealers across North Cyprus. Filter by make, model, year, mileage, fuel, gearbox and price, or let the AI rank the best deals.',
      'Each car shows an AI fair-price range from similar cars, a price battery and the market trend for that model, so you can tell a good price from a high one.',
    ],
    faq: [
      ['How do I know if a car price is fair?', 'Open the listing: the AI fair price range comes from similar cars of the same model and age, and the price battery shows how the price compares.'],
      ['Can I sell my car for free?', 'Yes. Private sellers list for free, and every listing gets an instant AI price check.'],
      ['What should I check before buying a used car?', 'See the car in person, check the registration documents and service history, and never pay before viewing it.'],
    ],
  },
  property: {
    title: 'Property for sale in North Cyprus',
    paragraphs: [
      'Browse apartments, villas, townhouses, penthouses and land for sale in North Cyprus. Filter by type, bedrooms, size, furnishing, pool, garden and price.',
      'Every property shows an AI fair-price range from comparable homes, the price trend for similar properties and an investment and risk signal.',
    ],
    faq: [
      ['How is the fair price of a property estimated?', 'We compare it with similar properties of the same type, size and number of bedrooms, and show the typical range of asking prices.'],
      ['Can foreigners buy property in North Cyprus?', 'Yes, foreign buyers can buy property, subject to permission from the authorities. Always use a licensed lawyer and check the title deed.'],
      ['What should I check before buying?', 'Check the title deed type, planning permits and any debts on the property with a lawyer before paying a deposit.'],
    ],
  },
} as const
