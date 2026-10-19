// Short excerpts from Jordan Peters' own published writing. Keep each source
// with its quote so the wording and attribution can be checked together.
export const jordanPetersQuotes = {
    progress: {
        text: 'All that matters is progress.',
        source: 'https://www.trainedbyjp.com/forums/reply/1391085/',
    },
    patience: {
        text: 'Diet and train hard and be patient.',
        source: 'https://www.trainedbyjp.com/forums/reply/1390755/',
    },
    recovery: {
        text: 'Recover & go again',
        source: 'https://www.trainedbyjp.com/product/jordan-peters-training-principles/',
    },
    ownPath: {
        text: 'I began to create my own path',
        source: 'https://www.trainedbyjp.com/product/jordan-peters-training-principles/',
    },
} as const;

export type JordanPetersQuote = {
    text: string;
    source: string;
};
