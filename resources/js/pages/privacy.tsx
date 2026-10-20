import PublicLayout from '@/layouts/public-layout';
import { useLocale, useT } from '@/lib/i18n';

const CONTENT = {
    en: [
        {
            h: 'What we read',
            p: 'When you add a Google Maps contributor profile, we open its public page once a day (like any visitor would) and read the numbers shown there: Local Guide level, points, reviews, photos, videos, photo views, places and roads added, plus the public name and profile picture. Nothing else — no reviews text, no locations, no private data.',
        },
        {
            h: 'Restricted profiles',
            p: 'If the “Restricted profile” setting is ON in Google Maps, your stats are hidden from everyone, including us. Turn it off in Google Maps → your profile → Edit profile if you want to appear on the leaderboard. We check again in the next daily update.',
        },
        {
            h: 'Photo frames',
            p: 'Photos you upload for a frame are re-encoded on our server and all metadata (including GPS location) is removed. Your original uploads are deleted automatically 30 days after you create a frame; the finished frame image stays available at its share link until you delete it from “My frames”.',
        },
        {
            h: 'Politeness',
            p: 'We read at most a few profiles per minute, once a day, so we never put load on Google Maps. This is a community project of Bangladesh Local Guides and is not affiliated with or endorsed by Google.',
        },
        {
            h: 'Removal',
            p: 'Want your profile removed from the leaderboard or a frame deleted? Delete your frames from “My frames”, or contact a community admin (see the community links on the home page) with your profile link and we will remove it.',
        },
    ],
    bn: [
        {
            h: 'আমরা কী পড়ি',
            p: 'আপনি গুগল ম্যাপস কন্ট্রিবিউটর প্রোফাইল যোগ করলে আমরা দিনে একবার (সাধারণ দর্শকের মতোই) এর পাবলিক পেজটি খুলে সেখানে দেখানো সংখ্যাগুলো পড়ি: লোকাল গাইড লেভেল, পয়েন্ট, রিভিউ, ছবি, ভিডিও, ছবির ভিউ, যোগ করা স্থান ও রাস্তা, এবং পাবলিক নাম ও প্রোফাইল ছবি। এর বাইরে কিছুই না — রিভিউয়ের লেখা, লোকেশন বা কোনো ব্যক্তিগত তথ্য নয়।',
        },
        {
            h: 'সীমাবদ্ধ প্রোফাইল',
            p: 'গুগল ম্যাপসে “Restricted profile” চালু থাকলে আপনার তথ্য সবার কাছ থেকে লুকানো থাকে, আমাদের কাছ থেকেও। লিডারবোর্ডে আসতে চাইলে গুগল ম্যাপস → আপনার প্রোফাইল → Edit profile থেকে এটি বন্ধ করুন। পরের দৈনিক আপডেটে আমরা আবার দেখব।',
        },
        {
            h: 'ফটো ফ্রেম',
            p: 'ফ্রেমের জন্য আপলোড করা ছবি আমাদের সার্ভারে নতুন করে এনকোড করা হয় এবং সব মেটাডেটা (GPS লোকেশনসহ) মুছে ফেলা হয়। ফ্রেম তৈরির ৩০ দিন পর মূল ছবিগুলো স্বয়ংক্রিয়ভাবে মুছে যায়; তৈরি ফ্রেমের ছবিটি শেয়ার লিংকে থাকে যতক্ষণ না আপনি “আমার ফ্রেম” থেকে মুছে ফেলেন।',
        },
        {
            h: 'ভদ্রতা',
            p: 'আমরা দিনে একবার, প্রতি মিনিটে মাত্র কয়েকটি প্রোফাইল পড়ি, তাই গুগল ম্যাপসের ওপর কোনো চাপ পড়ে না। এটি বাংলাদেশ লোকাল গাইডস কমিউনিটির একটি প্রজেক্ট, গুগলের সাথে সম্পর্কিত বা অনুমোদিত নয়।',
        },
        {
            h: 'মুছে ফেলা',
            p: 'লিডারবোর্ড থেকে আপনার প্রোফাইল সরাতে বা কোনো ফ্রেম মুছতে চান? “আমার ফ্রেম” থেকে ফ্রেম মুছুন, অথবা আপনার প্রোফাইল লিংকসহ একজন কমিউনিটি অ্যাডমিনের সাথে যোগাযোগ করুন (হোম পেজের কমিউনিটি লিংক দেখুন), আমরা সরিয়ে দেব।',
        },
    ],
};

export default function Privacy() {
    const t = useT();
    const locale = useLocale();

    return (
        <PublicLayout title={t('privacy.title')} className="max-w-3xl">
            <h1 className="text-2xl font-bold sm:text-3xl">{t('privacy.title')}</h1>
            <div className="mt-6 space-y-6">
                {CONTENT[locale].map((s) => (
                    <section key={s.h}>
                        <h2 className="text-lg font-semibold">{s.h}</h2>
                        <p className="text-muted-foreground mt-1 leading-relaxed">{s.p}</p>
                    </section>
                ))}
            </div>
        </PublicLayout>
    );
}
