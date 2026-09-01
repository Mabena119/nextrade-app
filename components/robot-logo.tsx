import React from 'react';
import { BrandLogo } from '@/components/brand-logo';

interface RobotLogoProps {
  size?: number;
}

export function RobotLogo({ size = 80 }: RobotLogoProps) {
  return <BrandLogo size={size} />;
}
