import React from 'react';
import { Image, StyleSheet, Text, View } from 'react-native';

import { useAppTheme } from '../context/AppContext';

export const authBackgroundImage = require('../../assets/healthlink-bg.jpg');
const brandLogoImage = require('../../assets/tubigon-logo.png');

type BrandMarkProps = {
  logoSize?: number;
  titleSize?: number;
  subtitleSize?: number;
  titleColor?: string;
  subtitleColor?: string;
};

export function BrandMark({
  logoSize = 112,
  titleSize = 32,
  subtitleSize = 24,
  titleColor,
  subtitleColor,
}: BrandMarkProps) {
  const theme = useAppTheme();

  return (
    <View style={styles.container}>
      <Image
        source={brandLogoImage}
        style={{ width: logoSize, height: logoSize }}
        resizeMode="contain"
      />
      <Text style={[styles.title, { fontSize: titleSize, color: titleColor ?? theme.colors.textOnBrand }]}>
        HEALTHLINK
      </Text>
      <Text
        style={[
          styles.subtitle,
          { fontSize: subtitleSize, color: subtitleColor ?? theme.colors.heroTextMuted },
        ]}
      >
        TUBIGON
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    alignItems: 'center',
  },
  title: {
    marginTop: 18,
    fontWeight: '800',
    letterSpacing: 1.4,
  },
  subtitle: {
    marginTop: 6,
    fontWeight: '800',
    letterSpacing: 1.1,
  },
});
