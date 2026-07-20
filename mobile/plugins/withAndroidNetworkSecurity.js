const fs = require('fs');
const path = require('path');
const {
  withAndroidManifest,
  withDangerousMod,
  createRunOncePlugin,
} = require('@expo/config-plugins');

const NETWORK_SECURITY_XML = `<?xml version="1.0" encoding="utf-8"?>
<network-security-config>
  <base-config cleartextTrafficPermitted="true" />
</network-security-config>
`;

function withAndroidManifestCleartext(config) {
  return withAndroidManifest(config, (config) => {
    const application = config.modResults.manifest.application?.[0];

    if (!application) {
      return config;
    }

    application.$ = application.$ || {};
    application.$['android:usesCleartextTraffic'] = 'true';
    application.$['android:networkSecurityConfig'] = '@xml/network_security_config';

    return config;
  });
}

function withAndroidNetworkSecurityFile(config) {
  return withDangerousMod(config, [
    'android',
    async (config) => {
      const projectRoot = config.modRequest.platformProjectRoot;
      const xmlDirectory = path.join(projectRoot, 'app', 'src', 'main', 'res', 'xml');

      await fs.promises.mkdir(xmlDirectory, { recursive: true });
      await fs.promises.writeFile(
        path.join(xmlDirectory, 'network_security_config.xml'),
        NETWORK_SECURITY_XML,
        'utf8',
      );

      return config;
    },
  ]);
}

const withAndroidNetworkSecurity = (config) => {
  config = withAndroidManifestCleartext(config);
  config = withAndroidNetworkSecurityFile(config);

  return config;
};

module.exports = createRunOncePlugin(
  withAndroidNetworkSecurity,
  'with-android-network-security',
  '1.0.0',
);
