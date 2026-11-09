<?php
declare(strict_types=1);
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#f7fbf8">
  <meta name="description" content="HSW365 — independent culture, streetwear, music, books and more.">
  <title>HSW365 — Official Store</title>
  <link rel="stylesheet" href="/assets/store.css">
  <script src="/assets/store.js" defer></script>
</head>
<body>
  <div class="announcement">HSW365 — YOUR BUSINESS, RUNNING 24/7 · CULTURE, CREATIVITY & COMMERCE</div>
  <header class="site-header">
    <a class="brand" href="/" aria-label="HSW365 home"><span>HSW</span><b>365</b></a>
    <nav aria-label="Main navigation">
      <a href="#merch">Merch</a><a href="#music">Music</a><a href="#books">Books</a><a href="#digital">Digital</a>
    </nav>
    <button class="cart-toggle" id="cart-toggle" type="button">Bag <span id="cart-count">0</span></button>
  </header>
  <main>
    <section class="hero">
      <div class="hero-copy">
        <p class="eyebrow">THE OFFICIAL HSW365 STORE</p>
        <h1>Built from the journey.<br><em>Made for what’s next.</em></h1>
        <p>Streetwear, music, books and digital creations. Independent culture. Real stories. One place.</p>
        <a class="primary-link" href="#shop">Shop the collection <span>↗</span></a>
      </div>
      <div class="hero-art" aria-label="HSW365 colorful brand artwork">
        <div class="orbit orbit-one"></div><div class="orbit orbit-two"></div>
        <div class="hero-mark">365</div><div class="hero-sticker">THE<br>JOURNEY<br>CONTINUES</div>
      </div>
    </section>
    <section class="category-strip" aria-label="Store categories">
      <a href="#merch">01 / MERCH</a><a href="#music">02 / MUSIC</a><a href="#books">03 / BOOKS</a><a href="#digital">04 / DIGITAL</a>
    </section>
    <section class="shop-section" id="shop">
      <div class="section-heading">
        <div><p class="eyebrow">THE COLLECTION</p><h2>Shop HSW365</h2></div>
        <label class="search-wrap"><span class="sr-only">Search products</span><input id="product-search" type="search" placeholder="Search the store"></label>
      </div>
      <div class="filters" id="category-filters">
        <button class="filter active" data-category="all">All</button>
        <button class="filter" data-category="merch" id="merch">Merch</button>
        <button class="filter" data-category="music" id="music">Music</button>
        <button class="filter" data-category="books" id="books">Books</button>
        <button class="filter" data-category="digital" id="digital">Digital</button>
      </div>
      <p class="notice" id="store-notice" role="status">Loading the collection…</p>
      <div class="product-grid" id="product-grid"></div>
    </section>
  </main>
  <footer><a class="brand footer-brand" href="/"><span>HSW</span><b>365</b></a><p>Independent culture. Real stories. Built to keep moving.</p><small>© <span id="year"></span> HSW365. All rights reserved.</small></footer>
  <aside class="cart-panel" id="cart-panel" aria-label="Shopping bag" aria-hidden="true">
    <div class="cart-head"><h2>Your bag</h2><button id="cart-close" aria-label="Close bag">×</button></div>
    <div id="cart-lines"></div>
    <div class="cart-bottom"><div class="cart-total"><span>Subtotal</span><strong id="cart-total">$0.00</strong></div><button class="checkout-button" id="checkout-button">Secure checkout</button><p>Shipping and taxes calculated at checkout.</p></div>
  </aside>
  <div class="scrim" id="scrim"></div>
</body>
</html>