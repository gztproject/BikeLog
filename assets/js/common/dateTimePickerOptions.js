const defaultIcons = {
    time: 'fa fa-clock',
    date: 'fa fa-calendar',
    up: 'fa fa-arrow-up',
    down: 'fa fa-arrow-down',
    previous: 'fa fa-arrow-left',
    next: 'fa fa-arrow-right',
    today: 'fa fa-calendar-day',
    clear: 'fa fa-backspace',
    close: 'fa fa-times',
};

function createDateTimePickerOptions(options = {}) {
    const { icons, ...rest } = options;

    return {
        locale: 'sl',
        icons: { ...defaultIcons, ...icons },
        ...rest,
    };
}

module.exports = {
    createDateTimePickerOptions,
    defaultIcons,
};
