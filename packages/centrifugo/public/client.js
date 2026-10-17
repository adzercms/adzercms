import { Centrifuge, UnauthorizedError } from 'centrifuge';

export const connect = ({endpoint, token, channels, renew}, event) => {
    const renewed = channel => renew(channel).then(value => {
        if (value === null) {
            throw new UnauthorizedError('the login gets no token');
        }

        return value;
    });
    const hub = new Centrifuge(endpoint, {token, getToken: () => renewed('')});

    channels.forEach(({channel, token: granted}) => {
        const options = granted ? {token: granted, getToken: context => renewed(context.channel)} : {};

        hub.newSubscription(channel, options)
            .on('publication', event)
            .on('subscribed', context => {
                if (context.wasRecovering && !context.recovered) {
                    event();
                }
            })
            .subscribe();
    });
    hub.connect();
};
