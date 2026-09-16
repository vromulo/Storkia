@php
$iconAttr = 'class="w-6 h-6 md:w-8 md:h-8 text-text-muted group-hover:text-primary group-hover:scale-110 transition-all duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"';

$categories = [
    'Pet' => [
        ['name' => 'Dog Food & Treats', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Dog', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>'],
        ['name' => 'Cat Litter & Accessories', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Cat', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5c4.69 0 8.5 3.81 8.5 8.5 0 2.34-.95 4.46-2.49 6H5.99A8.47 8.47 0 013.5 13c0-4.69 3.81-8.5 8.5-8.5zM12 2a10.5 10.5 0 00-10.5 10.5c0 2.91 1.18 5.54 3.09 7.46A1 1 0 005.3 21h13.4a1 1 0 00.71-1.71 10.45 10.45 0 003.09-7.46C22.5 6.26 17.74 2 12 2z"/></svg>'],
        ['name' => 'Aquariums & Fish Supplies', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Fish', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M20 12c-2 3-5 5-8 5s-6-2-8-5c2-3 5-5 8-5s6 2 8 5zm-8-2a2 2 0 100 4 2 2 0 000-4z"/></svg>'],
        ['name' => 'Bird Feeders & Food', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Bird', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v18m-6-6h12M5 8h14"/></svg>'],
        ['name' => 'Pet Grooming Products', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Groom', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h10a4 4 0 004-4v-4m-4-6h.01"/></svg>'],
        ['name' => 'Pet Health & Wellness', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Health', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>'],
    ],
    'Kids' => [
        ['name' => 'Baby Clothes & Accessories', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Baby', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>'],
        ['name' => 'Toys & Games', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Toys', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'],
        ['name' => 'Educational Materials', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Learn', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>'],
        ['name' => 'Strollers & Gear', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Gear', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 100-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/></svg>'],
        ['name' => 'Nursery Furniture', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Nursery', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>'],
        ['name' => 'Safety and Health', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Safety', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>'],
    ],
    'Electronics' => [
        ['name' => 'Mobile Phones & Accessories', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Phones', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>'],
        ['name' => 'Laptops, Desktops & Monitors', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Laptops', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>'],
        ['name' => 'Audio & Video Equipment', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Audio', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/></svg>'],
        ['name' => 'Smart Home Devices', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Smart', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>'],
        ['name' => 'Cameras & Photography', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Camera', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>'],
        ['name' => 'Wearable Technology', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Watch', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'],
    ],
    'Home & Garden' => [
        ['name' => 'Kitchen Appliances', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Kitchen', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L5.6 15.12a2 2 0 00-1.144.135l-1.028.455a2 2 0 00-1.17 1.832v.458c0 1.105.895 2 2 2h15.484a2 2 0 002-2v-.458a2 2 0 00-1.17-1.832l-1.344-.596z"/></svg>'],
        ['name' => 'Furniture & Decor', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Decor', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>'],
        ['name' => 'Gardening Tools', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Garden', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>'],
        ['name' => 'Outdoor Living', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Outdoor', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>'],
        ['name' => 'Home Improvement Tools', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Tools', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z"/></svg>'],
        ['name' => 'Bedding & Bath', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Bedding', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M5 3v16M19 3v16M5 11h14M5 7h14"/></svg>'],
    ],
    'Women\'s' => [
        ['name' => 'Dresses & Skirts', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Dress', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10l-2-10h-6l-2 10zM9 3h6v4H9V3z"/></svg>'],
        ['name' => 'Tops & Blouses', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Tops', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>'],
        ['name' => 'Activewear & Yoga Pants', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Yoga', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>'],
        ['name' => 'Lingerie & Sleepwear', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Sleep', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>'],
        ['name' => 'Jackets & Coats', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Coats', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M3 6l9-4 9 4v14a2 2 0 01-2 2H5a2 2 0 01-2-2V6z"/></svg>'],
        ['name' => 'Shoes & Accessories', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Shoes', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>'],
    ],
    'Men\'s' => [
        ['name' => 'Suits & Blazers', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Suits', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>'],
        ['name' => 'Casual Shirts & Pants', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Shirts', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h10M7 11h10M7 15h10"/></svg>'],
        ['name' => 'Outerwear & Jackets', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Jackets', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M3 6l9-4 9 4v14H3V6z"/></svg>'],
        ['name' => 'Activewear & Fitness Gear', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Fitness', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>'],
        ['name' => 'Shoes & Accessories', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Shoes', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01"/></svg>'],
        ['name' => 'Grooming Products', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Grooming', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879M12 12L9.121 9.121m0 5.758a3 3 0 11-4.243-4.243 3 3 0 014.243 4.243z"/></svg>'],
    ],
    'Health & Beauty' => [
        ['name' => 'Skincare Products', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Skin', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L5.6 15.12a2 2 0 00-1.144.135l-1.028.455a2 2 0 00-1.17 1.832v.458c0 1.105.895 2 2 2h15.484a2 2 0 002-2v-.458a2 2 0 00-1.17-1.832l-1.344-.596z"/></svg>'],
        ['name' => 'Haircare Solutions', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Hair', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h10a4 4 0 004-4v-4m-4-6h.01"/></svg>'],
        ['name' => 'Makeup & Cosmetics', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Makeup', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>'],
        ['name' => 'Personal Care Appliances', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Care', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>'],
        ['name' => 'Men\'s Grooming', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Mens', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879M12 12L9.121 9.121m0 5.758a3 3 0 11-4.243-4.243 3 3 0 014.243 4.243z"/></svg>'],
        ['name' => 'Health Supplements', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Health', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L5.6 15.12a2 2 0 00-1.144.135l-1.028.455a2 2 0 00-1.17 1.832v.458c0 1.105.895 2 2 2h15.484a2 2 0 002-2v-.458a2 2 0 00-1.17-1.832l-1.344-.596z"/></svg>'],
    ],
    'Books & Media' => [
        ['name' => 'Fiction & Non-Fiction Books', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Books', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>'],
        ['name' => 'Magazines & Periodicals', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Mags', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6m-6 4h6"/></svg>'],
        ['name' => 'Music CDs & Vinyl Records', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Music', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12 0c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>'],
        ['name' => 'Movie DVDs & Blu-ray', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Movies', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"/></svg>'],
        ['name' => 'Video Games & Consoles', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Games', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 002 2h14a2 2 0 002-2V7a2 2 0 00-2-2H5z"/></svg>'],
        ['name' => 'Educational DVDs', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Edu', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>'],
    ],
    'Sports & Outdoors' => [
        ['name' => 'Fitness Equipment', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Fitness', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>'],
        ['name' => 'Camping & Hiking Gear', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Camp', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M12 3L3 18h18L12 3z"/></svg>'],
        ['name' => 'Sports Apparel', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Apparel', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>'],
        ['name' => 'Cycling & Bikes', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Bike', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>'],
        ['name' => 'Water Sports', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Water', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M20 12c-2 3-5 5-8 5s-6-2-8-5c2-3 5-5 8-5s6 2 8 5z"/></svg>'],
        ['name' => 'Team Sports Equipment', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Team', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>'],
    ],
    'Food & Gourmet' => [
        ['name' => 'Baking Supplies & Ingredients', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Bake', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M21 15.546c-.523 0-1.046.151-1.5.454a2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.701 2.701 0 00-1.5-.454M9 6v2m3-2v2m3-2v2M9 3h.01M12 3h.01M15 3h.01M3 21h18a1 1 0 001-1v-5a1 1 0 00-1-1H3a1 1 0 00-1 1v5a1 1 0 001 1z"/></svg>'],
        ['name' => 'Coffee, Tea & Beverages', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Coffee', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M18 8h1a4 4 0 010 8h-1M2 8h16v9a4 4 0 01-4 4H6a4 4 0 01-4-4V8zM6 1v3m4-3v3m4-3v3"/></svg>'],
        ['name' => 'Snacks & Candy', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Snacks', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V6a2 2 0 10-2 2h2zm0 13C10.832 19.477 9.246 19 7.5 19S4.168 19.477 3 20.253V7.253C4.168 6.477 5.754 6 7.5 6s3.332.477 4.5 1.253"/></svg>'],
        ['name' => 'Specialty Foods', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Special', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>'],
        ['name' => 'Organic and Health Foods', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Organic', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>'],
        ['name' => 'Meal Kits & Prepped Foods', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Meals', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>'],
    ],
    'Furniture & Office' => [
        ['name' => 'Office Desks & Chairs', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Desks', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>'],
        ['name' => 'Storage Cabinets & Shelving', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Storage', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>'],
        ['name' => 'Conference & Meeting Furniture', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Meet', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>'],
        ['name' => 'Computer Tables & Workstations', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Tables', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>'],
        ['name' => 'Ergonomic Accessories', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Ergo', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'],
        ['name' => 'Office Lighting & Fixtures', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Lights', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>'],
    ],
    'Jewelry & Watches' => [
        ['name' => 'Necklaces & Pendants', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Necklace', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M12 15l-3-3m0 0l-3-3m3 3h8M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>'], // Placeholder for necklace
        ['name' => 'Rings & Engagement', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Rings', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 1.343-3 3s1.343 3 3 3 3-1.343 3-3-1.343-3-3-3zM12 2a10 10 0 100 20 10 10 0 000-20z"/></svg>'],
        ['name' => 'Earrings & Studs', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Earrings', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>'],
        ['name' => 'Men\'s Watches', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Watches', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'],
        ['name' => 'Women\'s Watches', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Watches', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'],
        ['name' => 'Fine Jewelry Sets', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Sets', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" '.$iconAttr.'><path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>'],
    ]
];
@endphp

<!-- Mega Menu / Category Navigation Bar -->
<div 
    x-data="{ 
        activeMenu: null,
        showLeft: false,
        showRight: true,
        init() {
            setTimeout(() => {
                const activeLink = this.$refs.slider.querySelector('.active-category');
                if (activeLink) {
                    activeLink.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                }
                this.updateArrows();
            }, 100);
            window.addEventListener('resize', () => this.updateArrows());
        },
        updateArrows() {
            const el = this.$refs.slider;
            this.showLeft = el.scrollLeft > 0;
            this.showRight = Math.ceil(el.scrollLeft + el.clientWidth) < el.scrollWidth - 1;
        },
        scroll(direction) {
            const el = this.$refs.slider;
            const scrollAmount = el.clientWidth * 0.8; 
            el.scrollBy({ 
                left: direction === 'left' ? -scrollAmount : scrollAmount, 
                behavior: 'smooth' 
            });
        },
        setActive(name) {
            this.activeMenu = name;
        }
    }" 
    @mouseleave="activeMenu = null"
    class="w-full bg-[#5D3140] relative z-20"
>
    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Inner wrapper to contain absolute positioned fades and arrows -->
        <div class="relative w-full">
            
            <!-- Left Gradient Fade -->
            <div 
                x-show="showLeft"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="absolute left-0 top-0 bottom-0 w-16 md:w-12 bg-gradient-to-r from-[#5D3140] to-transparent z-10 pointer-events-none"
                x-cloak
            ></div>

            <!-- Left Navigation Arrow (Shadow Removed) -->
            <button 
                type="button"
                x-show="showLeft" 
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                @click="scroll('left')" 
                class="absolute left-1 top-1/2 -translate-y-1/2 z-20 w-6 h-6 bg-black/30 backdrop-blur-sm rounded-full border border-white/30 text-white hover:text-[#F6D8BD] hover:border-[#F6D8BD] transition-colors flex items-center justify-center cursor-pointer"
                aria-label="Previous categories"
                x-cloak
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                </svg>
            </button>

            <!-- Navigation Links Container -->
            <nav 
                x-ref="slider" 
                @scroll.passive="updateArrows"
                class="flex items-center justify-center overflow-x-auto scroll-smooth [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none] relative z-0"
                aria-label="Category Navigation"
            >
                @foreach($categories as $categoryName => $subcategories)
                    @php 
                        $isActive = request()->is('category/' . Str::slug($categoryName)); 
                    @endphp
                    <a 
                        href="{{ url('/category/' . Str::slug($categoryName)) }}"
                        @mouseenter="setActive('{{ addslashes($categoryName) }}')"
                        @click="setActive('{{ addslashes($categoryName) }}')"
                        :class="activeMenu === '{{ addslashes($categoryName) }}' || {{ $isActive ? 'true' : 'false' }} 
                            ? 'bg-surface text-text-main font-bold' 
                            : 'text-white hover:bg-surface hover:text-text-main'"
                        class="flex-shrink-0 text-sm font-medium px-3 sm:px-3.5 py-1 rounded-none transition-all duration-150 whitespace-nowrap cursor-pointer {{ $isActive ? 'active-category' : '' }}"
                    >
                        {{ $categoryName }}
                    </a>
                @endforeach
            </nav>

            <!-- Right Gradient Fade -->
            <div 
                x-show="showRight" 
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="absolute right-0 top-0 bottom-0 w-16 md:w-12 bg-gradient-to-l from-[#5D3140] to-transparent z-10 pointer-events-none"
                x-cloak
            ></div>

            <!-- Right Navigation Arrow (Shadow Removed) -->
            <button 
                type="button"
                x-show="showRight" 
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                @click="scroll('right')" 
                class="absolute right-1 top-1/2 -translate-y-1/2 z-20 w-6 h-6 bg-black/30 backdrop-blur-sm rounded-full border border-white/30 text-white hover:text-[#F6D8BD] hover:border-[#F6D8BD] transition-colors flex items-center justify-center cursor-pointer"
                aria-label="Next categories"
                x-cloak
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                </svg>
            </button>
            
        </div>
    </div>

    <!-- Page Dark Overlay -->
    <div 
        x-show="activeMenu" 
        class="hidden md:block absolute top-full left-0 w-full h-[100vh] bg-gray-900/60 z-40 pointer-events-none"
        x-cloak
    ></div>

    <!-- The Mega Dropdown Component -->
    <x-mega-dropdown-menu :categories="$categories" />

</div>