# WriteMan – AI News Generator for WordPress

**WriteMan** is an autonomous news generation system for WordPress. It consumes RSS feeds, curates content using intelligent scoring, groups similar news, generates high-quality articles using AI (via Groq, OpenRouter, or Hugging Face), and publishes them automatically. It also includes A/B title testing, viral prediction, analytics, and a learning system.

## Features

- 🤖 AI-powered article generation (supports Groq, OpenRouter, Hugging Face)
- 📰 Multi-source RSS feed ingestion
- 🧠 Intelligent quality and viral scoring
- 🔥 A/B testing for titles (CTR optimization)
- 📈 Learning system (improves sources and keywords based on analytics)
- 🖼️ Automatic featured image (with fallback default image)
- 🌐 Supports 5 languages (Spanish, English, French, German, Portuguese)
- ⏱️ Flexible scheduling (minutes, hours, daily, weekly, monthly)
- 🗑️ Queue management (clear pending, view logs)
- 🔌 No external dependencies, runs inside WordPress

## Requirements

- WordPress 5.0+
- PHP 7.4+
- API key for Groq (free) or OpenRouter (free)

## Installation

1. Download the plugin zip.
2. Upload to `/wp-content/plugins/` and unzip, or install via WordPress admin.
3. Activate the plugin.
4. Go to **WriteMan → IA** and configure your AI provider (Groq recommended) with your API key.
5. Go to **WriteMan → Fuentes** and add your RSS feeds (each feed can have its own categories, tags, language, and author).
6. Save the feeds (automatic sync will enqueue articles).
7. Go to **WriteMan → Cola** and click "Ejecutar cola ahora" to generate articles.

## Configuration

### AI Provider
- **Groq** (recommended): fast, free, supports JSON mode. Models: `llama-3.3-70b-versatile`, `deepseek-r1-distill-llama-70b`.
- **OpenRouter**: free access to DeepSeek R1, etc.
- **Hugging Face**: fallback, less stable.

### Quality & Viral Thresholds
- Quality: 0-100 (minimum score for an article to be queued).
- Viral: 0-100 (minimum predicted viral score to generate).

### Publishing Options
- Post status (publish, draft, pending)
- Allow comments / trackbacks

### Scheduling
- Interval (every X minutes)
- Daily (select hours)
- Weekly (select days and hours)
- Monthly (select days of month and hours)

### Default Featured Image
- URL used when an RSS article has no image.

## Logs & Debugging
- The plugin writes a log to `/wp-content/uploads/writeman-debug.log`.
- You can download the log from the **Cola** tab.

## Frequently Asked Questions

**Does WriteMan require a paid API?**
No. Groq and OpenRouter offer free tiers sufficient for most sites. Hugging Face is also free but less reliable.

**Why are articles not generating?**
Check the queue table (Cola tab) for error messages. Also verify your API key and model name. Lower quality/viral thresholds temporarily to see if articles pass.

**Can I use other AI models?**
Yes. In the IA tab you can enter any model ID supported by your provider.

**Does it work with WP Cron?**
Yes. The plugin schedules a cron event to process the queue automatically every 5 minutes (or according to your schedule). You can also run manually.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Credits

Developed by **Jota** – [20xxnoticias.com](https://20xxnoticias.com)

## License

GPL v2 or later.
