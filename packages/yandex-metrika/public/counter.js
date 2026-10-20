export default ({tag, counter, options}) => {
    window.ym = window.ym || function () {
        (window.ym.a = window.ym.a || []).push(arguments);
    };
    window.ym.l = Date.now();

    const script = document.createElement('script');
    script.async = true;
    script.src = tag;
    document.head.append(script);

    window.ym(counter, 'init', options);
};
