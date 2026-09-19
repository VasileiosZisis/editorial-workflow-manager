(function () {
  const { __, sprintf } = wp.i18n;

  const countWords = (content) => {
    const text = String(content || '')
      .replace(/\[[^\]]*\]/g, ' ')
      .replace(/<[^>]*>/g, ' ')
      .replace(/&(?:#\d+|#x[a-f0-9]+|[a-z][a-z0-9]+);/gi, ' ');
    const matches = text.match(/[\p{L}\p{N}]+(?:[\u2019'-][\p{L}\p{N}]+)*/gu);
    return matches ? matches.length : 0;
  };

  window.EDIWORMAN_RULES.register('example/reading-time', {
    evaluate(context) {
      const wordCount = countWords(context.content);
      return {
        status: wordCount >= 200 ? 'pass' : 'fail',
        message: sprintf(
          /* translators: %d: current readable word count */
          __('%d readable words detected; 200 are required.', 'ediworman-reading-time-rule'),
          wordCount,
        ),
      };
    },
  });
})();
