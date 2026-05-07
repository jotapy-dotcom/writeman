# Changelog

All notable changes to WriteMan will be documented in this file.

## [1.0.3] – 2025-05-07

### Removed
- Social media integration (Twitter/X and Bluesky). These features will be offered as a separate plugin.

### Added
- Success notification when saving Scheduling settings.
- Error details now appear directly in the queue table.

### Fixed
- Minor adjustments to avoid conflicts with future updates.

## [1.0.2] – 2025-05-06

### Added
- Button to test RSS feed per source.
- Default featured image option (fallback when article has no image).
- Source URL column in the queue table.
- Duplicate detection: prevents posts with similar titles.

### Changed
- Improved AI prompt to generate longer articles (900+ words, multiple paragraphs).
- Increased `max_tokens` to 2000 for Groq and OpenRouter.
- Better logging in cron processes.

### Fixed
- AI connection test now accepts any valid JSON response (not only `{"status":"ok"}`).
- Image extraction from RSS enclosures now works more reliably.

## [1.0.1] – 2025-05-05

### Added
- Support for Groq (fast, free) and OpenRouter (DeepSeek R1) APIs.
- Hugging Face fallback.
- Queue system with status tracking.
- Quality and viral thresholds.
- A/B title testing.
- Analytics (views, clicks, CTR per source/keyword).
- Learning system (hot keywords, source scores).
- Multi‑language: Spanish, English, French, German, Portuguese.
- Cron scheduling: interval, daily, weekly, monthly.
- Manual controls: sync feeds, clear queue, run queue.
- Log download from admin.

### Fixed
- Initial release – core functionality working.