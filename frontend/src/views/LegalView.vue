<template>
  <MainLayout>
    <article class="legal max-w-3xl mx-auto px-4 sm:px-6 py-16" dir="ltr" lang="en">
      <template v-if="kind === 'privacy'">
        <h1>Privacy notice</h1>
        <p class="meta">Last updated: 4 November 2026</p>
        <p>This notice explains what AIPrice.app collects, why, and the choices you have. It applies to the website aiprice.app.
          We follow the personal data law of the Turkish Republic of Northern Cyprus and the principles of the EU GDPR and Turkish KVKK.</p>

        <h2>What we collect</h2>
        <ul>
          <li><strong>Account details</strong>: name, email address, password (stored only as a secure hash), account type and, if you add it, your phone number.</li>
          <li><strong>Listings</strong>: the text, photos, price and location you publish.</li>
          <li><strong>Messages</strong>: messages you send to sellers or owners through "Send to seller", and support or contact-form messages.</li>
          <li><strong>Phone number views</strong>: when a signed-in member opens a seller's full phone number we record who viewed it and when, to prevent scraping and abuse.</li>
          <li><strong>Identity checks (optional)</strong>: documents you upload for verification are stored privately and seen only by our verification staff.</li>
          <li><strong>Technical data</strong>: IP address and browser information in server logs, kept for security.</li>
        </ul>

        <h2>How we use it</h2>
        <ul>
          <li>To run your account and show your listings.</li>
          <li>To pass your message to the seller or owner. Your own phone and email are shared with them only if you tick the box to share them.</li>
          <li>To show a seller's phone number: it is partly hidden for visitors and shown in full only to signed-in members.</li>
          <li>To calculate price estimates. Estimates compare listings with similar ones; when there are too few, a description of the item (never your personal details) may be sent to OpenAI to produce a rough range.</li>
          <li>To answer support requests and to keep the marketplace safe from fraud.</li>
        </ul>
        <p>We do not sell personal data and we do not show third-party advertising based on your data.</p>

        <h2>Who can see it</h2>
        <p>AIPrice.app staff who handle support, moderation and verification. Service providers that host the site (Hostinger), send email,
          process payments (only if you buy a plan) and, for price estimates, OpenAI. Each receives only what it needs.</p>

        <h2>How long we keep it</h2>
        <p>Account data is kept while your account is open. Messages and phone-view records are kept for up to 24 months.
          When you delete your account we delete or anonymise your data within 30 days, except where the law requires us to keep it.</p>

        <h2>Your rights</h2>
        <p>You can ask to see, correct, export or delete your data, or object to how we use it. Most of it you can change yourself in Dashboard → Profile.
          For anything else, write to us through the <RouterLink to="/contact">contact page</RouterLink>. You can also complain to your data protection authority.</p>

        <h2>Cookies</h2>
        <p>We use only the cookies and browser storage needed to keep you signed in, protect forms, and remember your theme and language. No advertising or tracking cookies.</p>
      </template>

      <template v-else>
        <h1>Terms of use</h1>
        <p class="meta">Last updated: 4 November 2026</p>
        <p>By using AIPrice.app you agree to these terms. If you do not agree, please do not use the site.</p>

        <h2>What AIPrice.app is</h2>
        <p>AIPrice.app is a marketplace where people list cars and property in North Cyprus. We are not a party to any sale or rental:
          the agreement is between the buyer and the seller or owner.</p>

        <h2>Price estimates</h2>
        <p>Price estimates and scores are produced automatically from comparable listings and, when data is scarce, by an AI model.
          They are <strong>estimates only</strong>, not a valuation, appraisal, inspection or guarantee. Always inspect the item, check the documents and,
          for property, the title deed, before you pay anything.</p>

        <h2>Your listings</h2>
        <ul>
          <li>You must own the item or be authorised to sell or rent it, and the details and photos must be true and current.</li>
          <li>No illegal items, misleading prices, duplicate listings or content you do not have the rights to.</li>
          <li>We may review, edit for formatting, hide or remove listings that break these rules.</li>
        </ul>

        <h2>Your account</h2>
        <p>Keep your password private. You are responsible for what happens in your account. We may suspend accounts used for fraud, spam or harassment.</p>

        <h2>Messages and phone numbers</h2>
        <p>Use sellers' contact details only to ask about their listing. Collecting numbers for marketing or spam is not allowed and leads to suspension.</p>

        <h2>Paid plans</h2>
        <p>Paid plans renew until cancelled. You can cancel at any time in Dashboard → Subscription; the plan stays active until the end of the paid period.</p>

        <h2>Liability</h2>
        <p>We work to keep the site accurate and available but provide it "as is". We are not liable for the condition, legality or price of items listed by users,
          or for losses from transactions between users, to the extent the law allows.</p>

        <h2>Contact</h2>
        <p>Questions about these terms: use the <RouterLink to="/contact">contact page</RouterLink>.</p>
      </template>
    </article>
  </MainLayout>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import MainLayout from '@/layouts/MainLayout.vue'

const route = useRoute()
const kind = computed(() => (route.name === 'privacy' ? 'privacy' : 'terms'))
</script>

<style scoped>
.legal { color:var(--text-secondary);line-height:1.7;font-size:15px;text-align:left }
.legal h1 { font-size:2.2rem;font-weight:900;color:var(--text-primary);margin-bottom:4px }
.legal h2 { font-size:1.15rem;font-weight:800;color:var(--text-primary);margin:28px 0 8px }
.legal .meta { font-size:13px;color:var(--text-muted);margin-bottom:20px }
.legal ul { list-style:disc;padding-left:20px;display:grid;gap:6px }
.legal a { color:var(--accent-cyan);text-decoration:underline }
.legal p { margin-bottom:10px }
</style>
