(function (window) {
  const registrations = new Map();
  const ruleIdPattern = /^[a-z][a-z0-9_-]{1,31}\/[a-z][a-z0-9_-]{1,63}$/;
  const statuses = new Set(['pass', 'fail', 'not_applicable']);

  const register = (ruleId, definition) => {
    if (
      typeof ruleId !== 'string' ||
      !ruleIdPattern.test(ruleId) ||
      !definition ||
      typeof definition.evaluate !== 'function' ||
      registrations.has(ruleId)
    ) {
      return false;
    }

    registrations.set(ruleId, { evaluate: definition.evaluate });
    return true;
  };

  const evaluate = (ruleId, context) => {
    const definition = registrations.get(ruleId);
    if (!definition) {
      return null;
    }

    try {
      const result = definition.evaluate(context);
      if (
        !result ||
        typeof result !== 'object' ||
        !statuses.has(result.status) ||
        (result.message !== undefined && typeof result.message !== 'string')
      ) {
        return { status: 'fail', message: '' };
      }

      return {
        status: result.status,
        message: typeof result.message === 'string' ? result.message : '',
      };
    } catch (error) {
      return { status: 'fail', message: '' };
    }
  };

  window.EDIWORMAN_RULES = Object.freeze({ register, evaluate });
})(window);
